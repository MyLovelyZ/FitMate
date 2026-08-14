<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(50, 500) * 1000;
        $quantity = fake()->numberBetween(1, 3);

        return [
            'store_order_id' => StoreOrder::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_name' => fake()->words(3, true),
            'size_label' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'color_name' => fake()->randomElement(['Hitam', 'Putih', 'Navy']),
            'unit_price' => $price,
            'quantity' => $quantity,
            'subtotal' => $price * $quantity,
            'recommended_size_label' => null,
        ];
    }
}
