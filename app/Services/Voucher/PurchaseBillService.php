<?php

namespace App\Services\Voucher;

use App\Exceptions\NotFoundException;
use App\Exceptions\WorkflowException;
use App\Models\Vouchers\PurchaseBill;
use App\Services\Accounts\AccountingService;
use App\Services\Inventory\StockService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseBillService
{
    public function __construct(
        protected StockService $stockService,
        protected AccountingService $accountingService,
    ) {}

    protected array $relations = [
        'organization',
        'branch',
        'supplier',
        'voucherType',
        'createdBy',
        'items',
    ];

    public function paginate(?array $filters, int $perPage): LengthAwarePaginator
    {
        return PurchaseBill::query()
            ->with($this->relations)
            ->when(isset($filters['organization_id']), fn($q) => $q->where('organization_id', $filters['organization_id']))
            ->when(isset($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(isset($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['outlet_id']), fn($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(isset($filters['supplier_id']), fn($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn($q) => $q->whereDate('date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn($q) => $q->whereDate('date', '<=', $filters['date_to']))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $term = '%' . trim($filters['search']) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('document_number', 'like', $term)
                        ->orWhereHas('supplier', fn($cq) => $cq->where('name', 'like', $term));
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id, ?int $orgId = null, bool $withTrashed = false): PurchaseBill
    {
        $query = PurchaseBill::query();
        $query->with($this->relations);

        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        return $query->find($id) ?? throw new NotFoundException('Purchase Bill not found.');
    }

    public function create(array $data): PurchaseBill
    {
        return DB::transaction(function () use ($data) {
            $purchaseBill = PurchaseBill::create($data);

            $purchaseBill->logEvent('created', null, $data);

            return $purchaseBill->load($this->relations);
        });
    }

    public function update(PurchaseBill $purchaseBill, array $data): PurchaseBill
    {
        if (!$purchaseBill->isEditable()) {
            throw new WorkflowException('Purchase Bill cannot be edited in its current status: ' . $purchaseBill->status);
        }

        return DB::transaction(function () use ($purchaseBill, $data) {
            $oldData = $purchaseBill->toArray();

            $purchaseBill->update($data);
            $purchaseBill->logEvent('updated', $oldData, $purchaseBill->toArray());

            return $purchaseBill->load($this->relations);
        });
    }

    public function delete(PurchaseBill $purchaseBill): void
    {
        if (!$purchaseBill->isDeletable()) {
            throw new WorkflowException('Purchase Bill cannot be deleted in its current status.');
        }

        $purchaseBill->delete();
        $purchaseBill->logEvent('deleted', null, null);
    }

    public function restore(PurchaseBill $purchaseBill): void
    {
        $purchaseBill->restore();
        $purchaseBill->logEvent('restored', null, null);
    }

    public function forceDelete(PurchaseBill $purchaseBill): void
    {
        $purchaseBill->forceDelete();
    }

    public function submitForApproval(PurchaseBill $purchaseBill, ?string $note = null): PurchaseBill
    {
        if (!$purchaseBill->canTransitionTo('submitted')) {
            throw new WorkflowException('Purchase Bill cannot be submitted from status: ' . $purchaseBill->status);
        }

        return DB::transaction(function () use ($purchaseBill, $note) {
            $purchaseBill->markSubmitted();

            if ($note) {
                $purchaseBill->addComment($note, true);
            }

            $purchaseBill->logEvent('submitted', null, ['status' => $purchaseBill->status]);

            return $purchaseBill;
        });
    }

    public function review(PurchaseBill $purchaseBill, ?string $note = null, ?int $userId = null): PurchaseBill
    {
        if (!$purchaseBill->isReviewable()) {
            throw new WorkflowException('Purchase Bill is not in a reviewable state.');
        }

        return DB::transaction(function () use ($purchaseBill, $note, $userId) {
            $purchaseBill->markReviewed($userId);
            $purchaseBill->changeStatus('reviewed', $note);

            if ($note) {
                $purchaseBill->addComment($note, true);
            }

            $purchaseBill->logEvent('reviewed', null, ['reviewed_by' => $userId ?? Auth::id()]);

            return $purchaseBill;
        });
    }

    public function approve(PurchaseBill $purchaseBill, ?string $note = null, ?int $userId = null): PurchaseBill
    {
        if (!$purchaseBill->isApprovable()) {
            throw new WorkflowException('Purchase Bill is not in an approvable state.');
        }

        return DB::transaction(function () use ($purchaseBill, $note, $userId) {
            $purchaseBill->markApproved($userId);
            $purchaseBill->changeStatus('approved', $note);

            if ($note) {
                $purchaseBill->addComment($note, true);
            }

            $purchaseBill->logEvent('approved', null, ['approved_by' => $userId ?? Auth::id()]);

            $fresh = $purchaseBill->fresh();
            event(new \App\Events\Voucher\VoucherApproved([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'purchase_bill',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $userId ?? $fresh->created_by ?? null,
                'items_count' => method_exists($fresh, 'items') ? $fresh->items()->count() : null,
            ]));

            return $purchaseBill;
        });
    }

    public function reject(PurchaseBill $purchaseBill, string $reason, ?string $note = null, ?int $userId = null): PurchaseBill
    {
        if (!$purchaseBill->canTransitionTo('rejected')) {
            throw new WorkflowException('Purchase Bill cannot be rejected from status: ' . $purchaseBill->status);
        }

        return DB::transaction(function () use ($purchaseBill, $reason, $note, $userId) {
            $purchaseBill->markRejected($reason, null, $userId);

            if ($note) {
                $purchaseBill->addComment($note, true);
            }

            $purchaseBill->logEvent('rejected', null, [
                'reason' => $reason,
                'rejected_by' => $userId ?? Auth::id(),
            ]);

            return $purchaseBill;
        });
    }

    public function cancel(PurchaseBill $purchaseBill, string $reason): PurchaseBill
    {
        if (!$purchaseBill->canTransitionTo('cancelled')) {
            throw new WorkflowException('Purchase Bill cannot be cancelled from status: ' . $purchaseBill->status);
        }

        return DB::transaction(function () use ($purchaseBill, $reason) {
            $purchaseBill->changeStatus('cancelled', $reason);

            $purchaseBill->logEvent('cancelled', null, ['reason' => $reason]);

            $fresh = $purchaseBill->fresh();
            event(new \App\Events\Voucher\VoucherCancelled([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'purchase_bill',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $fresh->created_by ?? null,
                'reason' => $reason,
            ]));

            return $purchaseBill;
        });
    }

    public function post(int $id): ?PurchaseBill
    {
        $purchaseBill = PurchaseBill::find($id);
        if (!$purchaseBill) {
            return null;
        }

        DB::transaction(function () use ($purchaseBill) {
            $this->stockService->increaseStockForDocument(get_class($purchaseBill), $purchaseBill->id, $purchaseBill->created_by ?? auth()->id());

            $this->accountingService->createGlForPurchaseBill($purchaseBill);

            $purchaseBill->status = 'posted';
            $purchaseBill->save();
        });

        $fresh = $purchaseBill->fresh();

        event(new \App\Events\Voucher\VoucherPosted([
            'reference_type' => get_class($fresh),
            'reference_id' => $fresh->id,
            'document_type' => 'purchase_bill',
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
