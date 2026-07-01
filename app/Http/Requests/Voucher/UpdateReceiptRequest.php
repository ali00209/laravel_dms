<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'sometimes|integer|exists:organizations,id',
            'customer_id' => 'sometimes|integer|exists:customers,id',
            'voucher_type_id' => 'sometimes|integer|exists:voucher_types,id',
            'amount' => 'sometimes|numeric|min:0.01',
            'date' => 'sometimes|date',
            'account_id' => 'sometimes|integer|exists:accounts,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
