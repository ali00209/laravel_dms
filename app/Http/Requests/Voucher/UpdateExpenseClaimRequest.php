<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'sometimes|integer|exists:organizations,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'voucher_type_id' => 'sometimes|integer|exists:voucher_types,id',
            'date' => 'sometimes|date',
            'payee_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'account_id' => 'sometimes|integer|exists:accounts,id',
            'items' => 'sometimes|array|min:1',
            'items.*.account_id' => 'required|integer|exists:accounts,id',
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.description' => 'nullable|string|max:500',
            'items.*.receipt_reference' => 'nullable|string|max:255',
        ];
    }
}
