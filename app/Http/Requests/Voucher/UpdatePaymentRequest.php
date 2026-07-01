<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'sometimes|integer|exists:organizations,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'voucher_type_id' => 'sometimes|integer|exists:voucher_types,id',
            'amount' => 'sometimes|numeric|min:0.01',
            'date' => 'sometimes|date',
            'account_id' => 'sometimes|integer|exists:accounts,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'reference_number' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'payment_type' => 'nullable|string|max:50',
            'cheque_number' => 'nullable|string|max:50',
            'expense_account_id' => 'nullable|integer|exists:accounts,id',
            'notes' => 'nullable|string',
        ];
    }
}
