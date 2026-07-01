<?php

namespace Database\Factories\Inventory;

use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = \App\Models\Inventory\Unit::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'short_name' => $this->faker->lexify('??'),
            'allow_decimal' => false,
        ];
    }
}
