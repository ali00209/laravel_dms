<?php

namespace App\Http\Resources\Voucher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseClaimItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_claim_id' => $this->expense_claim_id,
            'account_id' => $this->account_id,
            'account_name' => $this->whenLoaded('account', fn() => $this->account->name),
            'amount' => $this->amount,
            'description' => $this->description,
            'receipt_reference' => $this->receipt_reference,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
