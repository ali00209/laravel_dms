<?php

namespace App\Http\Requests\Inventory;

use App\Models\Inventory\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BulkOpeningStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string'],
            'items.*.manufacturing_date' => ['nullable', 'date'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $items = $this->input('items', []);

            if (empty($items)) {
                return;
            }

            $variantIds = array_unique(array_column($items, 'product_variant_id'));
            $variants = ProductVariant::with('product')
                ->whereIn('id', $variantIds)
                ->get()
                ->keyBy('id');

            $seenBatches = [];

            foreach ($items as $index => $item) {
                $variantId = $item['product_variant_id'] ?? null;
                $prefix = "items.{$index}";

                if (! $variantId || ! isset($variants[$variantId])) {
                    $v->errors()->add("{$prefix}.product_variant_id", "Variant #{$variantId} not found.");
                    continue;
                }

                $variant = $variants[$variantId];
                $valuationMethod = $variant->product->valuation_method;

                if (in_array($valuationMethod, ['FIFO', 'FEFO']) && empty($item['batch_number'])) {
                    $v->errors()->add(
                        "{$prefix}.batch_number",
                        "Batch number is required for {$valuationMethod} valuation (variant #{$variantId})."
                    );
                }

                if (! empty($item['manufacturing_date']) && ! empty($item['expiry_date'])) {
                    if ($item['expiry_date'] < $item['manufacturing_date']) {
                        $v->errors()->add(
                            "{$prefix}.expiry_date",
                            "Expiry date must be after or equal to manufacturing date."
                        );
                    }
                }

                $batchKey = $variantId . '|' . ($item['batch_number'] ?? '');
                if (! empty($item['batch_number'])) {
                    if (isset($seenBatches[$batchKey])) {
                        $v->errors()->add(
                            "{$prefix}.batch_number",
                            "Duplicate batch number '{$item['batch_number']}' for variant #{$variantId} within this request."
                        );
                    }
                    $seenBatches[$batchKey] = true;
                }
            }
        });
    }
}
