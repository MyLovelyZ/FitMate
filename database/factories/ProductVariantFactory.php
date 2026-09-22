<?php

namespace Database\Factories;

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'size_id' => Size::factory(),
            'color_id' => Color::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-???')),
            'price' => null, // ikut base_price produknya
            'stock' => fake()->numberBetween(1, 50),
            'image' => null,
            'is_active' => true,
        ];
    }

    /**
     * Varian produk aksesoris — ngk punya ukuran maupun warna.
     */
    public function withoutSizing(): static
    {
        return $this->state(fn (array $attributes) => [
            'size_id' => null,
            'color_id' => null,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
