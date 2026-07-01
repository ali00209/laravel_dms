<?php

namespace App\Services\Voucher;

use App\Exceptions\NotFoundException;
use App\Exceptions\WorkflowException;
use App\Models\Accounts\Account;
use App\Models\Vouchers\Receipt;
use App\Models\Vouchers\ReceiptReference;
use App\Models\Vouchers\VoucherType;
use App\Services\Accounts\AccountingService;
use App\Services\Accounts\AccountMappingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    protected array $relations = [
        'customer',
        'account',
        'voucherType',
        'journal',
        'references',
    ];

    public function __construct(
        protected AccountingService $accountingService,
        protected AccountMappingService $accountMappingService,
    ) {}

    public function paginate(?array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Receipt::query()
            ->with($this->relations)
            ->when(isset($filters['organization_id']), fn($q) => $q->where('organization_id', $filters['organization_id']))
            ->when(isset($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(isset($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['outlet_id']), fn($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(isset($filters['customer_id']), fn($q) => $q->where('customer_id', $filters['customer_id']))
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
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id, ?int $orgId = null, bool $withTrashed = false): Receipt
    {
        $query = Receipt::query();
        $query->with($this->relations);

        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        return $query->find($id) ?? throw new NotFoundException('Receipt not found.');
    }

    public function create(array $data): Receipt
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateDocumentNumber($data['voucher_type_id']);
            }

            $data['status'] = 'draft';
            $data['unallocated_amount'] = $data['amount'];

            $receipt = Receipt::create($data);
            $receipt->logEvent('created', null, $data);

            return $receipt->load($this->relations);
        });
    }

    public function update(Receipt $receipt, array $data): Receipt
    {
        if (!$receipt->isEditable()) {
            throw new WorkflowException('Receipt cannot be edited in its current status: ' . $receipt->status);
        }

        return DB::transaction(function () use ($receipt, $data) {
            $oldData = $receipt->toArray();

            if (isset($data['amount']) && $data['amount'] != $receipt->amount) {
                $data['unallocated_amount'] = $data['amount'] - ($receipt->amount - $receipt->unallocated_amount);
            }

            $receipt->update($data);
            $receipt->logEvent('updated', $oldData, $receipt->toArray());

            return $receipt->load($this->relations);
        });
    }

    public function delete(Receipt $receipt): void
    {
        if (!$receipt->isDeletable()) {
            throw new WorkflowException('Receipt cannot be deleted in its current status.');
        }

        $receipt->delete();
        $receipt->logEvent('deleted', null, null);
    }

    public function restore(int $id): void
    {
        $receipt = Receipt::withTrashed()->findOrFail($id);
        $receipt->restore();
        $receipt->logEvent('restored', null, null);
    }

    public function forceDelete(int $id): void
    {
        $receipt = Receipt::withTrashed()->findOrFail($id);
        $receipt->forceDelete();
    }

    public function post(Receipt $receipt): Receipt
    {
        if ($receipt->status !== 'draft') {
            throw new WorkflowException('Only draft receipts can be posted.');
        }

        return DB::transaction(function () use ($receipt) {
            $receivableAccount = $this->getReceivableAccount($receipt->organization_id);
            $cashAccount = $receipt->account;

            if (!$receivableAccount) {
                throw new WorkflowException('No accounts receivable account found for this organization.');
            }

            $lines = [
                [
                    'account_id' => $cashAccount->id,
                    'debit' => (float) $receipt->amount,
                    'credit' => 0,
                    'memo' => 'Receipt from customer: ' . ($receipt->customer?->name ?? 'N/A'),
                ],
                [
                    'account_id' => $receivableAccount->id,
                    'debit' => 0,
                    'credit' => (float) $receipt->amount,
                    'memo' => 'Receipt: ' . $receipt->document_number,
                ],
            ];

            $journal = $this->accountingService->createJournalForVoucher(
                'receipt',
                $receipt->id,
                $lines,
                $receipt->created_by ?? Auth::id()
            );

            $receipt->journal_id = $journal->id;
            $receipt->status = 'posted';
            $receipt->save();

            $receipt->logEvent('posted', null, [
                'status' => 'posted',
                'journal_id' => $journal->id,
            ]);

            $fresh = $receipt->fresh();

            event(new \App\Events\Voucher\ReceiptPosted([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'receipt',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $fresh->created_by ?? null,
                'amount' => $fresh->amount,
                'journal_id' => $journal->id,
            ]));

            return $fresh;
        });
    }

    public function cancel(Receipt $receipt, string $reason): Receipt
    {
        if (!$receipt->canTransitionTo('cancelled')) {
            throw new WorkflowException('Receipt cannot be cancelled from status: ' . $receipt->status);
        }

        return DB::transaction(function () use ($receipt, $reason) {
            $receipt->changeStatus('cancelled', $reason);
            $receipt->logEvent('cancelled', null, ['reason' => $reason]);

            return $receipt->fresh();
        });
    }

    public function allocate(Receipt $receipt, array $references): Receipt
    {
        if ($receipt->status !== 'draft') {
            throw new WorkflowException('Only draft receipts can be allocated.');
        }

        return DB::transaction(function () use ($receipt, $references) {
            foreach ($references as $ref) {
                ReceiptReference::create([
                    'receipt_id' => $receipt->id,
                    'reference_type' => $ref['reference_type'],
                    'reference_id' => $ref['reference_id'],
                    'amount' => $ref['amount'],
                ]);
            }

            $receipt->updateUnallocated();

            return $receipt->load($this->relations);
        });
    }

    protected function getReceivableAccount(int $organizationId): ?Account
    {
        return Account::where('organization_id', $organizationId)
            ->where('type', 'Asset')
            ->where(function ($q) {
                $q->where('name', 'like', '%Receivable%')
                  ->orWhere('code', 'like', '12%');
            })
            ->where('is_active', true)
            ->first() ?? $this->accountMappingService->getReceivableAccount($organizationId);
    }

    protected function generateDocumentNumber(int $voucherTypeId): string
    {
        $voucherType = VoucherType::findOrFail($voucherTypeId);
        $prefix = $voucherType->prefix ?? 'RCT';
        $nextNumber = $voucherType->next_number ?? 1;

        $voucherType->increment('next_number');

        return $prefix . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
