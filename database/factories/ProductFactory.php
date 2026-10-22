<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'store_id' => Store::factory(),
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'gender' => 'unisex',
            'base_price' => fake()->numberBetween(50, 2000) * 1000,
            'compare_at_price' => null,
            'is_active' => true,
        ];
    }

    public function men(): static
    {
        return $this->state(fn (array $attributes) => ['gender' => 'men']);
    }

    public function women(): static
    {
        return $this->state(fn (array $attributes) => ['gender' => 'women']);
    }

    /**
     * Harga coret 25% di atas harga jual.
     */
    public function onSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'compare_at_price' => round($attributes['base_price'] * 1.25, -3),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
