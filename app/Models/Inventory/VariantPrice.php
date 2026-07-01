<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VariantPrice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_variant_id',
        'priceable_type',
        'priceable_id',
        'sale_price',
    ];

    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }
}
