<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'required|integer|exists:organizations,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'voucher_type_id' => 'required|integer|exists:voucher_types,id',
            'date' => 'required|date',
            'payee_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'account_id' => 'required|integer|exists:accounts,id',
            'items' => 'required|array|min:1',
            'items.*.account_id' => 'required|integer|exists:accounts,id',
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.description' => 'nullable|string|max:500',
            'items.*.receipt_reference' => 'nullable|string|max:255',
        ];
    }
}
