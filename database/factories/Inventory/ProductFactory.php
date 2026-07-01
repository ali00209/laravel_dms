<?php

namespace Database\Factories\Inventory;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = \App\Models\Inventory\Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'valuation_method' => 'FIFO',
            'is_active' => true,
            'has_variants' => false,
            'has_warranty' => false,
        ];
    }
}
