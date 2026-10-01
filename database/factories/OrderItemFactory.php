<?php

namespace Database\Factories;

use App\Models\OrderItem;
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
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = fake()->numberBetween(50, 900) * 1000;

        return [
            'store_order_id' => StoreOrder::factory(),
            'product_id' => null,
            'product_variant_id' => null,
            'product_name' => fake()->words(3, true),
            'variant_sku' => strtoupper(fake()->bothify('FM-####-??')),
            'size_label' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'color_name' => fake()->randomElement(['Hitam', 'Navy', 'Krem']),
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => $unitPrice * $quantity,
            'recommended_size_label' => null,
        ];
    }

    /**
     * Item yang disalin dari varian sungguhan, seperti hasil checkout beneran.
     */
    public function fromVariant(ProductVariant $variant, int $quantity = 1): static
    {
        return $this->state(function (array $attributes) use ($variant, $quantity) {
            $variant->loadMissing(['product', 'size', 'color']);
            $unitPrice = (float) ($variant->price ?? $variant->product->base_price);

            return [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_sku' => $variant->sku,
                'size_label' => $variant->size?->name,
                'color_name' => $variant->color?->name,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $unitPrice * $quantity,
            ];
        });
    }
}
