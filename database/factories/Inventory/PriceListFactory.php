<?php

namespace Database\Factories\Inventory;

use Illuminate\Database\Eloquent\Factories\Factory;

class PriceListFactory extends Factory
{
    protected $model = \App\Models\Inventory\PriceList::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true) . ' Price List',
            'currency' => 'PKR',
            'is_default' => false,
            'is_active' => true,
            'priority' => 1,
        ];
    }
}
