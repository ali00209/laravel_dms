<?php

namespace App\Http\Resources\Voucher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn() => $this->customer->name),
            'voucher_type_id' => $this->voucher_type_id,
            'voucher_type_prefix' => $this->whenLoaded('voucherType', fn() => $this->voucherType->prefix),
            'document_number' => $this->document_number,
            'amount' => $this->amount,
            'unallocated_amount' => $this->unallocated_amount,
            'date' => $this->date?->toDateString(),
            'account_id' => $this->account_id,
            'account_name' => $this->whenLoaded('account', fn() => $this->account->name),
            'reference_number' => $this->reference_number,
            'status' => $this->status,
            'journal_id' => $this->journal_id,
            'references' => ReceiptReferenceResource::collection($this->whenLoaded('references')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
