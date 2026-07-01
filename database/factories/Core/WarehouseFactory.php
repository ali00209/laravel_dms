<?php

namespace Database\Factories\Core;

use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = \App\Models\Core\Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Warehouse',
            'code' => $this->faker->unique()->bothify('WH-####'),
            'country' => 'Pakistan',
            'is_active' => true,
        ];
    }
}
