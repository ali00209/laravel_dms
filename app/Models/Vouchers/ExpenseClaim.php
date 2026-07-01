<?php

namespace App\Models\Vouchers;

use App\Models\Accounts\Account;
use App\Models\Accounts\Journal;
use App\Models\Core\Branch;

class ExpenseClaim extends BaseVoucher
{
    protected $table = 'expense_claims';

    protected $fillable = [
        'organization_id', 'branch_id', 'warehouse_id', 'outlet_id', 'financial_year_id', 'voucher_type_id',
        'document_number', 'date', 'amount', 'payee_name', 'description',
        'status', 'account_id', 'journal_id',
        'created_by', 'updated_by', 'approved_by', 'approved_at',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function items()
    {
        return $this->hasMany(ExpenseClaimItem::class);
    }
}
