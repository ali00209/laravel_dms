<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Brand;
use App\Models\Inventory\BrandModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class BrandModelFactory extends Factory
{
    protected $model = BrandModel::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'brand_id' => Brand::factory(),
            'name' => ucfirst($name),
            'series' => 'Series '.strtoupper($this->faker->randomLetter()),
            'slug' => str()->slug($name),
            'is_active' => true,
        ];
    }
}
