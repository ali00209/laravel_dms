<?php

namespace App\Http\Resources\Voucher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'branch_id' => $this->branch_id,
            'financial_year_id' => $this->financial_year_id,
            'voucher_type_id' => $this->voucher_type_id,
            'document_number' => $this->document_number,
            'date' => $this->date?->toDateString(),
            'amount' => $this->amount,
            'payee_name' => $this->payee_name,
            'description' => $this->description,
            'status' => $this->status,
            'account_id' => $this->account_id,
            'account_name' => $this->whenLoaded('account', fn() => $this->account->name),
            'journal_id' => $this->journal_id,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'items' => ExpenseClaimItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
