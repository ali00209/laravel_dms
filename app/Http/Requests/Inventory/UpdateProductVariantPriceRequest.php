<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\BaseFormRequest;

class UpdateProductVariantPriceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['sometimes','required', 'integer', 'exists:organizations,id'],
            'product_variant_id' => ['sometimes','required', 'integer', 'exists:product_variants,id'],
            'priceable_type' => ['sometimes','required', 'string'],
            'priceable_id' => ['sometimes','required', 'integer'],
            'cost_price' => ['nullable', 'numeric'],
            'sale_price' => ['nullable', 'numeric'],
            'is_override' => ['boolean'],
        ];
    }
}

