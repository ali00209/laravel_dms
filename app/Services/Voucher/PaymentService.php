<?php

namespace App\Services\Voucher;

use App\Exceptions\NotFoundException;
use App\Exceptions\WorkflowException;
use App\Models\Accounts\Account;
use App\Models\Vouchers\Payment;
use App\Models\Vouchers\PaymentReference;
use App\Models\Vouchers\VoucherType;
use App\Services\Accounts\AccountingService;
use App\Services\Accounts\AccountMappingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    protected array $relations = [
        'supplier',
        'account',
        'voucherType',
        'journal',
        'references',
        'payable',
    ];

    public function __construct(
        protected AccountingService $accountingService,
        protected AccountMappingService $accountMappingService,
    ) {}

    public function paginate(?array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Payment::query()
            ->with($this->relations)
            ->when(isset($filters['organization_id']), fn($q) => $q->where('organization_id', $filters['organization_id']))
            ->when(isset($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(isset($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['outlet_id']), fn($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(isset($filters['supplier_id']), fn($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn($q) => $q->whereDate('date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn($q) => $q->whereDate('date', '<=', $filters['date_to']))
            ->when(isset($filters['payment_type']), fn($q) => $q->where('payment_type', $filters['payment_type']))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $term = '%' . trim($filters['search']) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('document_number', 'like', $term)
                        ->orWhereHas('supplier', fn($cq) => $cq->where('name', 'like', $term))
                        ->orWhere('description', 'like', $term)
                        ->orWhere('payable_type', 'like', $term);
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id, ?int $orgId = null, bool $withTrashed = false): Payment
    {
        $query = Payment::query();
        $query->with($this->relations);

        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        return $query->find($id) ?? throw new NotFoundException('Payment not found.');
    }

    public function create(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateDocumentNumber($data['voucher_type_id']);
            }

            if (empty($data['payment_type'])) {
                $voucherType = VoucherType::findOrFail($data['voucher_type_id']);
                $prefix = strtoupper($voucherType->prefix ?? '');
                $data['payment_type'] = match (true) {
                    str_starts_with($prefix, 'CPV'), str_starts_with($prefix, 'BPV') => 'supplier_payment',
                    str_starts_with($prefix, 'EXP') => 'expense_payment',
                    str_starts_with($prefix, 'SLV') => 'salary_payment',
                    default => 'supplier_payment',
                };
            }

            $data['status'] = 'draft';
            $data['unallocated_amount'] = $data['amount'];

            $payment = Payment::create($data);
            $payment->logEvent('created', null, $data);

            return $payment->load($this->relations);
        });
    }

    public function update(Payment $payment, array $data): Payment
    {
        if (!$payment->isEditable()) {
            throw new WorkflowException('Payment cannot be edited in its current status: ' . $payment->status);
        }

        return DB::transaction(function () use ($payment, $data) {
            $oldData = $payment->toArray();

            if (isset($data['amount']) && $data['amount'] != $payment->amount) {
                $data['unallocated_amount'] = $data['amount'] - ($payment->amount - $payment->unallocated_amount);
            }

            $payment->update($data);
            $payment->logEvent('updated', $oldData, $payment->toArray());

            return $payment->load($this->relations);
        });
    }

    public function delete(Payment $payment): void
    {
        if (!$payment->isDeletable()) {
            throw new WorkflowException('Payment cannot be deleted in its current status.');
        }

        $payment->delete();
        $payment->logEvent('deleted', null, null);
    }

    public function restore(int $id): void
    {
        $payment = Payment::withTrashed()->findOrFail($id);
        $payment->restore();
        $payment->logEvent('restored', null, null);
    }

    public function forceDelete(int $id): void
    {
        $payment = Payment::withTrashed()->findOrFail($id);
        $payment->forceDelete();
    }

    public function post(Payment $payment): Payment
    {
        if ($payment->status !== 'draft') {
            throw new WorkflowException('Only draft payments can be posted.');
        }

        return DB::transaction(function () use ($payment) {
            $cashAccount = $payment->account;

            if (!$cashAccount) {
                throw new WorkflowException('No cash/bank account found for this payment.');
            }

            $paymentType = $payment->payment_type;
            $lines = [];

            if ($paymentType === 'expense_payment' || !$payment->supplier_id) {
                $expenseAccount = $payment->expense_account_id
                    ? Account::find($payment->expense_account_id)
                    : $this->getExpenseAccount($payment->organization_id);

                if (!$expenseAccount) {
                    throw new WorkflowException('No expense account found for expense payment.');
                }

                $lines = [
                    [
                        'account_id' => $expenseAccount->id,
                        'debit' => (float) $payment->amount,
                        'credit' => 0,
                        'memo' => $payment->description ?? 'Expense payment: ' . $payment->document_number,
                    ],
                    [
                        'account_id' => $cashAccount->id,
                        'debit' => 0,
                        'credit' => (float) $payment->amount,
                        'memo' => 'Payment: ' . $payment->document_number,
                    ],
                ];
            } elseif ($paymentType === 'salary_payment') {
                $salaryPayable = $this->getSalaryPayableAccount($payment->organization_id);

                if (!$salaryPayable) {
                    throw new WorkflowException('No Salary Payable account found for this organization. Create one under Current Liabilities (e.g., 2.1.5).');
                }

                $lines = [
                    [
                        'account_id' => $salaryPayable->id,
                        'debit' => (float) $payment->amount,
                        'credit' => 0,
                        'memo' => $payment->description ?? 'Salary disbursement: ' . $payment->document_number,
                    ],
                    [
                        'account_id' => $cashAccount->id,
                        'debit' => 0,
                        'credit' => (float) $payment->amount,
                        'memo' => 'Payment: ' . $payment->document_number,
                    ],
                ];
            } else {
                $payableAccount = $this->getPayableAccount($payment->organization_id);

                if (!$payableAccount) {
                    throw new WorkflowException('No accounts payable account found for this organization.');
                }

                $lines = [
                    [
                        'account_id' => $payableAccount->id,
                        'debit' => (float) $payment->amount,
                        'credit' => 0,
                        'memo' => 'Payment to supplier: ' . ($payment->supplier?->name ?? 'N/A'),
                    ],
                    [
                        'account_id' => $cashAccount->id,
                        'debit' => 0,
                        'credit' => (float) $payment->amount,
                        'memo' => 'Payment: ' . $payment->document_number,
                    ],
                ];
            }

            $journal = $this->accountingService->createJournalForVoucher(
                'payment',
                $payment->id,
                $lines,
                $payment->created_by ?? Auth::id()
            );

            $payment->journal_id = $journal->id;
            $payment->status = 'posted';
            $payment->save();

            $payment->logEvent('posted', null, [
                'status' => 'posted',
                'journal_id' => $journal->id,
            ]);

            $fresh = $payment->fresh();

            event(new \App\Events\Voucher\PaymentPosted([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'payment',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $fresh->created_by ?? null,
                'amount' => $fresh->amount,
                'journal_id' => $journal->id,
            ]));

            return $fresh;
        });
    }

    public function cancel(Payment $payment, string $reason): Payment
    {
        if (!$payment->canTransitionTo('cancelled')) {
            throw new WorkflowException('Payment cannot be cancelled from status: ' . $payment->status);
        }

        return DB::transaction(function () use ($payment, $reason) {
            $payment->changeStatus('cancelled', $reason);
            $payment->logEvent('cancelled', null, ['reason' => $reason]);

            return $payment->fresh();
        });
    }

    public function allocate(Payment $payment, array $references): Payment
    {
        if ($payment->status !== 'draft') {
            throw new WorkflowException('Only draft payments can be allocated.');
        }

        return DB::transaction(function () use ($payment, $references) {
            foreach ($references as $ref) {
                PaymentReference::create([
                    'payment_id' => $payment->id,
                    'reference_type' => $ref['reference_type'],
                    'reference_id' => $ref['reference_id'],
                    'amount' => $ref['amount'],
                ]);
            }

            $payment->updateUnallocated();

            return $payment->load($this->relations);
        });
    }

    protected function getPayableAccount(int $organizationId): ?Account
    {
        return Account::where('organization_id', $organizationId)
            ->where('type', 'Liability')
            ->where(function ($q) {
                $q->where('name', 'like', '%Payable%')
                  ->orWhere('code', 'like', '2%');
            })
            ->where('is_active', true)
            ->first();
    }

    protected function getExpenseAccount(int $organizationId): ?Account
    {
        return Account::where('organization_id', $organizationId)
            ->where('type', 'Expense')
            ->where('is_active', true)
            ->where('is_group', false)
            ->first();
    }

    protected function getSalaryPayableAccount(int $organizationId): ?Account
    {
        return Account::where('organization_id', $organizationId)
            ->where('type', 'Liability')
            ->where('name', 'like', '%Salary%')
            ->where('is_active', true)
            ->where('is_group', false)
            ->first();
    }

    protected function generateDocumentNumber(int $voucherTypeId): string
    {
        $voucherType = VoucherType::findOrFail($voucherTypeId);
        $prefix = $voucherType->prefix ?? 'PMT';
        $nextNumber = $voucherType->next_number ?? 1;

        $voucherType->increment('next_number');

        return $prefix . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
