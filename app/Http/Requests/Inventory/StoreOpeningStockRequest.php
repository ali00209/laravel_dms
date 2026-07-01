<?php

namespace App\Http\Requests\Inventory;

use App\Models\Inventory\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpeningStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'batch_number' => ['nullable', 'string', Rule::unique('inventory_batches')->where(fn($q) => $q->where('product_variant_id', $this->product_variant_id))],
            'manufacturing_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:manufacturing_date'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            if (! $this->product_variant_id) {
                return;
            }

            $variant = ProductVariant::with('product')->find($this->product_variant_id);

            if (! $variant) {
                $v->errors()->add('product_variant_id', 'Variant not found.');
                return;
            }

            $valuationMethod = $variant->product->valuation_method;

            if (in_array($valuationMethod, ['FIFO', 'FEFO']) && ! $this->batch_number) {
                $v->errors()->add('batch_number', "Batch number is required for {$valuationMethod} valuation.");
            }
        });
    }
}
