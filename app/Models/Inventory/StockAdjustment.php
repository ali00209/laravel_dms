<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'stock_location_id', 'product_variant_id',
        'inventory_batch_id', 'adjustment_type', 'quantity', 'unit_cost',
        'reason', 'status', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'approved_at' => 'datetime',
    ];
}
