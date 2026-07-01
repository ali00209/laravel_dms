<?php

namespace App\Listeners;

use App\Events\Voucher\ExpenseClaimPosted;
use Illuminate\Support\Facades\Log;

class HandleExpenseClaimPosted
{
    public function handle(ExpenseClaimPosted $event): void
    {
        Log::info('ExpenseClaimPosted', $event->payload);
    }
}
