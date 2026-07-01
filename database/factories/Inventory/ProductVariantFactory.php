<?php

namespace Database\Factories\Inventory;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = \App\Models\Inventory\ProductVariant::class;

    public function definition(): array
    {
        return [
            'sku' => $this->faker->unique()->bothify('VAR-####-????'),
            'cost_price' => $this->faker->randomFloat(2, 50, 200),
            'sale_price' => $this->faker->randomFloat(2, 100, 500),
            'is_serial_tracked' => false,
            'is_active' => true,
        ];
    }
}
