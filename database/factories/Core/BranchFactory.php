<?php

namespace Database\Factories\Core;

use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = \App\Models\Core\Branch::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Branch',
            'code' => $this->faker->unique()->bothify('BR-####'),
            'country' => 'Pakistan',
            'is_active' => true,
        ];
    }
}
