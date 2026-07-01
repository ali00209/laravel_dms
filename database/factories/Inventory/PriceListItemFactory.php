<?php

namespace Database\Factories\Inventory;

use Illuminate\Database\Eloquent\Factories\Factory;

class PriceListItemFactory extends Factory
{
    protected $model = \App\Models\Inventory\PriceListItem::class;

    public function definition(): array
    {
        return [
            'price' => $this->faker->randomFloat(2, 10, 1000),
        ];
    }
}
