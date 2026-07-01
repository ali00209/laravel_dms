<?php

namespace App\Http\Resources\Voucher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->whenLoaded('supplier', fn() => $this->supplier->name),
            'voucher_type_id' => $this->voucher_type_id,
            'voucher_type_prefix' => $this->whenLoaded('voucherType', fn() => $this->voucherType->prefix),
            'document_number' => $this->document_number,
            'amount' => $this->amount,
            'unallocated_amount' => $this->unallocated_amount,
            'date' => $this->date?->toDateString(),
            'account_id' => $this->account_id,
            'account_name' => $this->whenLoaded('account', fn() => $this->account->name),
            'reference_number' => $this->reference_number,
            'cheque_number' => $this->cheque_number,
            'status' => $this->status,
            'journal_id' => $this->journal_id,
            'references' => PaymentReferenceResource::collection($this->whenLoaded('references')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
