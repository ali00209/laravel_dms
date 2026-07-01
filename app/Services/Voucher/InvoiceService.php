<?php

namespace App\Services\Voucher;

use App\Exceptions\NotFoundException;
use App\Exceptions\WorkflowException;
use App\Models\Vouchers\Invoice;
use App\Services\Accounts\AccountingService;
use App\Services\Inventory\StockService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        protected StockService $stockService,
        protected AccountingService $accountingService,
    ) {}

    protected array $relations = [
        'customer',
        'branch',
        'voucherType',
        'journal',
        'posSession',
        'creditNotes',
    ];

    public function paginate(?array $filters, int $perPage): LengthAwarePaginator
    {
        return Invoice::query()
            ->with($this->relations)
            ->when(isset($filters['organization_id']), fn($q) => $q->where('organization_id', $filters['organization_id']))
            ->when(isset($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(isset($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['outlet_id']), fn($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(isset($filters['customer_id']), fn($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['voucher_type_id']), fn($q) => $q->where('voucher_type_id', $filters['voucher_type_id']))
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn($q) => $q->whereDate('date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn($q) => $q->whereDate('date', '<=', $filters['date_to']))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $term = '%' . trim($filters['search']) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('document_number', 'like', $term)
                        ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', $term));
                });
            })
            ->when(isset($filters['need_review']), fn($q) => $q->needReview())
            ->when(isset($filters['need_approval']), fn($q) => $q->needApproval())
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id, ?int $orgId = null, bool $withTrashed = false): Invoice
    {
        $query = Invoice::query();
        $query->with($this->relations);

        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        return $query->find($id) ?? throw new NotFoundException('Invoice not found.');
    }

    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['document_number'])) {
                // Document number generation would go here
            }

            $invoice = Invoice::create($data);

            $invoice->logEvent('created', null, $data);

            return $invoice->load($this->relations);
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        if (!$invoice->isEditable()) {
            throw new WorkflowException('Invoice cannot be edited in its current status: ' . $invoice->status);
        }

        return DB::transaction(function () use ($invoice, $data) {
            $oldData = $invoice->toArray();

            $invoice->update($data);
            $invoice->logEvent('updated', $oldData, $invoice->toArray());

            return $invoice->load($this->relations);
        });
    }

    public function delete(Invoice $invoice): void
    {
        if (!$invoice->isDeletable()) {
            throw new WorkflowException('Invoice cannot be deleted in its current status.');
        }

        $invoice->delete();
        $invoice->logEvent('deleted', null, null);
    }

    public function restore(Invoice $invoice): void
    {
        $invoice->restore();
        $invoice->logEvent('restored', null, null);
    }

    public function forceDelete(Invoice $invoice): void
    {
        $invoice->forceDelete();
    }

    public function submitForApproval(Invoice $invoice, ?string $note = null): Invoice
    {
        if (!$invoice->canTransitionTo('submitted')) {
            throw new WorkflowException('Invoice cannot be submitted from status: ' . $invoice->status);
        }

        return DB::transaction(function () use ($invoice, $note) {
            $invoice->markSubmitted();

            if ($note) {
                $invoice->addComment($note, true);
            }

            $invoice->logEvent('submitted', null, ['status' => $invoice->status]);

            return $invoice;
        });
    }

    public function review(Invoice $invoice, ?string $note = null, ?int $userId = null): Invoice
    {
        if (!$invoice->isReviewable()) {
            throw new WorkflowException('Invoice is not in a reviewable state.');
        }

        return DB::transaction(function () use ($invoice, $note, $userId) {
            $invoice->markReviewed($userId);
            $invoice->changeStatus('reviewed', $note);

            if ($note) {
                $invoice->addComment($note, true);
            }

            $invoice->logEvent('reviewed', null, ['reviewed_by' => $userId ?? Auth::id()]);

            return $invoice;
        });
    }

    public function approve(Invoice $invoice, ?string $note = null, ?int $userId = null): Invoice
    {
        if (!$invoice->isApprovable()) {
            throw new WorkflowException('Invoice is not in an approvable state.');
        }

        return DB::transaction(function () use ($invoice, $note, $userId) {
            $invoice->markApproved($userId);
            $invoice->changeStatus('approved', $note);

            if ($note) {
                $invoice->addComment($note, true);
            }

            $invoice->logEvent('approved', null, ['approved_by' => $userId ?? Auth::id()]);

            $fresh = $invoice->fresh();
            event(new \App\Events\Voucher\VoucherApproved([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'invoice',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $userId ?? $fresh->created_by ?? null,
                'items_count' => method_exists($fresh, 'items') ? $fresh->items()->count() : null,
            ]));

            return $invoice;
        });
    }

    public function reject(Invoice $invoice, string $reason, ?string $note = null, ?int $userId = null): Invoice
    {
        if (!$invoice->canTransitionTo('rejected')) {
            throw new WorkflowException('Invoice cannot be rejected from status: ' . $invoice->status);
        }

        return DB::transaction(function () use ($invoice, $reason, $note, $userId) {
            $invoice->markRejected($reason, null, $userId);

            if ($note) {
                $invoice->addComment($note, true);
            }

            $invoice->logEvent('rejected', null, [
                'reason' => $reason,
                'rejected_by' => $userId ?? Auth::id(),
            ]);

            return $invoice;
        });
    }

    public function cancel(Invoice $invoice, string $reason): Invoice
    {
        if (!$invoice->canTransitionTo('cancelled')) {
            throw new WorkflowException('Invoice cannot be cancelled from status: ' . $invoice->status);
        }

        return DB::transaction(function () use ($invoice, $reason) {
            $invoice->changeStatus('cancelled', $reason);

            $invoice->logEvent('cancelled', null, ['reason' => $reason]);

            $fresh = $invoice->fresh();
            event(new \App\Events\Voucher\VoucherCancelled([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'invoice',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $fresh->created_by ?? null,
                'reason' => $reason,
            ]));

            return $invoice;
        });
    }

    public function confirm(Invoice $invoice, ?string $note = null): Invoice
    {
        if ($invoice->status !== 'approved') {
            throw new WorkflowException('Only approved invoices can be confirmed.');
        }

        return DB::transaction(function () use ($invoice, $note) {
            $this->stockService->reduceStockForDocument(get_class($invoice), $invoice->id, $invoice->created_by ?? auth()->id());
            $this->accountingService->createGlForInvoice($invoice);

            $invoice->changeStatus('confirmed', $note);
            $invoice->logEvent('confirmed', null, ['status' => $invoice->status]);

            return $invoice;
        });
    }

    public function post(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'confirmed') {
            throw new WorkflowException('Only confirmed invoices can be posted.');
        }

        DB::transaction(function () use ($invoice) {
            $invoice->status = 'posted';
            $invoice->save();
        });

        $fresh = $invoice->fresh();

        event(new \App\Events\Voucher\VoucherPosted([
            'reference_type' => get_class($fresh),
            'reference_id' => $fresh->id,
            'document_type' => 'invoice',
            'organization_id' => $fresh->organization_id ?? null,
            'branch_id' => $fresh->branch_id ?? null,
            'created_by' => $fresh->created_by ?? null,
            'items_count' => method_exists($fresh, 'items') ? $fresh->items()->count() : null,
            'totals' => [
                'net' => $fresh->total ?? $fresh->net_total ?? null,
            ],
        ]));

        return $fresh;
    }
}
