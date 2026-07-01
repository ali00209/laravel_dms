<?php

namespace App\Http\Requests\Voucher;

use App\Http\Requests\BaseFormRequest;
use App\Models\Partner\Customer;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
            'customer_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:customers,id',
                function ($attribute, $value, $fail) {
                    $customer = Customer::find($value);
                    if ($customer && !$customer->is_active) {
                        $fail('The selected customer is inactive.');
                    }
                }
            ],
            'currency_code' => ['nullable', 'string', 'max:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'date' => ['sometimes', 'required', 'date', 'date_format:Y-m-d'],
            'sub_total' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'grand_total' => ['nullable', 'numeric', 'min:0'],
            'action' => ['nullable', 'string', Rule::in(['submit', 'review', 'approve', 'reject', 'cancel'])],
            'reason' => ['required_if:action,reject,cancel', 'nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required_if' => 'Reason is required when rejecting or cancelling.',
        ];
    }
}
