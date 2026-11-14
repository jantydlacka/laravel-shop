<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => Str::ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'price' => fake()->numberBetween(99, 4999) * 100,
            'stock' => fake()->numberBetween(1, 20),
            'is_available' => true,
            'image_path' => null,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }

    public function unavailable(): static
    {
        return $this->state(['is_available' => false]);
    }
}
