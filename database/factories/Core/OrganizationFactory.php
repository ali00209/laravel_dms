<?php

namespace Database\Factories\Core;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = \App\Models\Core\Organization::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'country' => 'Pakistan',
            'currency_code' => 'PKR',
            'is_active' => true,
            'policies_locked' => false,
        ];
    }
}
