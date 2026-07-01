<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class StoreReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'required|integer|exists:organizations,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'voucher_type_id' => 'required|integer|exists:voucher_types,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'account_id' => 'required|integer|exists:accounts,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
