<?php

namespace App\Services\Voucher;

use App\Exceptions\NotFoundException;
use App\Exceptions\WorkflowException;
use App\Models\Accounts\Account;
use App\Models\Vouchers\ExpenseClaim;
use App\Models\Vouchers\ExpenseClaimItem;
use App\Models\Vouchers\VoucherType;
use App\Services\Accounts\AccountingService;
use App\Services\Accounts\AccountMappingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseClaimService
{
    protected array $relations = [
        'branch',
        'account',
        'journal',
        'items.account',
    ];

    public function __construct(
        protected AccountingService $accountingService,
        protected AccountMappingService $accountMappingService,
    ) {}

    public function paginate(?array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return ExpenseClaim::query()
            ->with($this->relations)
            ->when(isset($filters['organization_id']), fn($q) => $q->where('organization_id', $filters['organization_id']))
            ->when(isset($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(isset($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['outlet_id']), fn($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(isset($filters['payee_name']), fn($q) => $q->where('payee_name', 'like', '%' . $filters['payee_name'] . '%'))
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn($q) => $q->whereDate('date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn($q) => $q->whereDate('date', '<=', $filters['date_to']))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $term = '%' . trim($filters['search']) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('document_number', 'like', $term)
                        ->orWhere('payee_name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id, ?int $orgId = null, bool $withTrashed = false): ExpenseClaim
    {
        $query = ExpenseClaim::query();
        $query->with($this->relations);

        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        return $query->find($id) ?? throw new NotFoundException('Expense claim not found.');
    }

    public function create(array $data): ExpenseClaim
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateDocumentNumber($data['voucher_type_id']);
            }

            $data['status'] = 'draft';
            $data['amount'] = collect($items)->sum('amount');

            $claim = ExpenseClaim::create($data);

            foreach ($items as $item) {
                ExpenseClaimItem::create([
                    'expense_claim_id' => $claim->id,
                    'account_id' => $item['account_id'],
                    'amount' => $item['amount'],
                    'description' => $item['description'] ?? null,
                    'receipt_reference' => $item['receipt_reference'] ?? null,
                ]);
            }

            $claim->logEvent('created', null, $data);

            return $claim->load($this->relations);
        });
    }

    public function update(ExpenseClaim $claim, array $data): ExpenseClaim
    {
        if (!$claim->isEditable()) {
            throw new WorkflowException('Expense claim cannot be edited in its current status: ' . $claim->status);
        }

        return DB::transaction(function () use ($claim, $data) {
            $oldData = $claim->toArray();
            $items = $data['items'] ?? null;
            unset($data['items']);

            if ($items !== null) {
                $data['amount'] = collect($items)->sum('amount');
            }

            $claim->update($data);

            if ($items !== null) {
                $claim->items()->delete();
                foreach ($items as $item) {
                    ExpenseClaimItem::create([
                        'expense_claim_id' => $claim->id,
                        'account_id' => $item['account_id'],
                        'amount' => $item['amount'],
                        'description' => $item['description'] ?? null,
                        'receipt_reference' => $item['receipt_reference'] ?? null,
                    ]);
                }
            }

            $claim->logEvent('updated', $oldData, $claim->toArray());

            return $claim->load($this->relations);
        });
    }

    public function delete(ExpenseClaim $claim): void
    {
        if (!$claim->isDeletable()) {
            throw new WorkflowException('Expense claim cannot be deleted in its current status.');
        }

        $claim->delete();
        $claim->logEvent('deleted', null, null);
    }

    public function restore(int $id): void
    {
        $claim = ExpenseClaim::withTrashed()->findOrFail($id);
        $claim->restore();
        $claim->logEvent('restored', null, null);
    }

    public function forceDelete(int $id): void
    {
        $claim = ExpenseClaim::withTrashed()->findOrFail($id);
        $claim->forceDelete();
    }

    public function post(ExpenseClaim $claim): ExpenseClaim
    {
        if ($claim->status !== 'draft') {
            throw new WorkflowException('Only draft expense claims can be posted.');
        }

        return DB::transaction(function () use ($claim) {
            $claim->load('items');
            $cashAccount = $claim->account;

            if (!$cashAccount) {
                throw new WorkflowException('No cash account found for this expense claim.');
            }

            $totalAmount = (float) $claim->amount;
            $lines = [];

            foreach ($claim->items as $item) {
                $lines[] = [
                    'account_id' => $item->account_id,
                    'debit' => (float) $item->amount,
                    'credit' => 0,
                    'memo' => 'Expense claim: ' . ($item->description ?? $claim->description ?? $claim->document_number),
                ];
            }

            $lines[] = [
                'account_id' => $cashAccount->id,
                'debit' => 0,
                'credit' => $totalAmount,
                'memo' => 'Expense claim: ' . $claim->document_number,
            ];

            $journal = $this->accountingService->createJournalForVoucher(
                'expense_claim',
                $claim->id,
                $lines,
                $claim->created_by ?? Auth::id()
            );

            $claim->journal_id = $journal->id;
            $claim->status = 'posted';
            $claim->save();

            $claim->logEvent('posted', null, [
                'status' => 'posted',
                'journal_id' => $journal->id,
            ]);

            $fresh = $claim->fresh();

            event(new \App\Events\Voucher\ExpenseClaimPosted([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'expense_claim',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $fresh->created_by ?? null,
                'amount' => $fresh->amount,
                'journal_id' => $journal->id,
            ]));

            return $fresh;
        });
    }

    public function cancel(ExpenseClaim $claim, string $reason): ExpenseClaim
    {
        if (!$claim->canTransitionTo('cancelled')) {
            throw new WorkflowException('Expense claim cannot be cancelled from status: ' . $claim->status);
        }

        return DB::transaction(function () use ($claim, $reason) {
            $claim->changeStatus('cancelled', $reason);
            $claim->logEvent('cancelled', null, ['reason' => $reason]);

            return $claim->fresh();
        });
    }

    protected function generateDocumentNumber(int $voucherTypeId): string
    {
        $voucherType = VoucherType::findOrFail($voucherTypeId);
        $prefix = $voucherType->prefix ?? 'EXP';
        $nextNumber = $voucherType->next_number ?? 1;

        $voucherType->increment('next_number');

        return $prefix . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
