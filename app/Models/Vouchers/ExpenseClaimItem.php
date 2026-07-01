<?php

namespace App\Models\Vouchers;

use App\Models\Accounts\Account;
use Illuminate\Database\Eloquent\Model;

class ExpenseClaimItem extends Model
{
    protected $table = 'expense_claim_items';

    protected $fillable = [
        'expense_claim_id', 'account_id', 'amount', 'description', 'receipt_reference',
    ];

    public function expenseClaim()
    {
        return $this->belongsTo(ExpenseClaim::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
