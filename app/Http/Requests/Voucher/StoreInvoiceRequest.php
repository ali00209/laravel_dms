<?php

namespace App\Http\Requests\Voucher;

use App\Http\Requests\BaseFormRequest;
use App\Models\Partner\Customer;
use App\Models\Vouchers\VoucherType;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
                function ($attribute, $value, $fail) {
                    $customer = Customer::find($value);
                    if ($customer && !$customer->is_active) {
                        $fail('The selected customer is inactive.');
                    }
                }
            ],
            'voucher_type_id' => [
                'required',
                'integer',
                'exists:voucher_types,id',
                function ($attribute, $value, $fail) {
                    $voucherType = VoucherType::find($value);
                    if ($voucherType && $voucherType->module !== 'sale') {
                        $fail('The selected voucher type is not valid for invoices.');
                    }
                }
            ],
            'financial_year_id' => ['required', 'integer', 'exists:financial_years,id'],
            'currency_code' => ['nullable', 'string', 'max:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'sub_total' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'grand_total' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'submitted'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('status')) {
            $this->merge(['status' => 'draft']);
        }
    }

    public function messages(): array
    {
        return [
            'voucher_type_id.required' => 'Voucher type is required.',
            'financial_year_id.required' => 'Financial year is required.',
        ];
    }
}
