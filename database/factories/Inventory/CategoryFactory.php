<?php

namespace Database\Factories\Inventory;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = \App\Models\Inventory\Category::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
        ];
    }
}
