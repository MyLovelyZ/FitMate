<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'size_chart_entry_id' => null,
            'sku' => Str::upper(fake()->unique()->bothify('SKU-####-????')),
            'color_name' => fake()->randomElement(['Hitam', 'Putih', 'Navy', 'Abu']),
            'color_hex' => fake()->hexColor(),
            'price' => fake()->numberBetween(50, 500) * 1000,
            'compare_at_price' => null,
            'stock' => fake()->numberBetween(1, 50),
            'weight_gram' => null,
            'image' => null,
            'is_active' => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock' => 0,
        ]);
    }
}
