<?php

namespace App\Services\Inventory\Contracts;

use App\Models\Inventory\ProductVariant;

interface ValuationStrategyInterface
{
    public function consume(array $payload, ProductVariant $variant): void;
}
