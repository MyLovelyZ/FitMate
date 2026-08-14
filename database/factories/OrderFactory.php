<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(50, 500) * 1000;
        $shipping = 20000;

        return [
            'user_id' => User::factory(),
            'order_number' => Order::generateOrderNumber(),
            'coupon_id' => null,
            'recipient_name' => fake()->name(),
            'recipient_phone' => fake()->numerify('08##########'),
            'shipping_street' => fake()->streetAddress(),
            'shipping_city' => fake()->city(),
            'shipping_state' => fake()->state(),
            'shipping_postal_code' => fake()->numerify('#####'),
            'shipping_country' => 'Indonesia',
            'subtotal' => $subtotal,
            'shipping_total' => $shipping,
            'discount_total' => 0,
            'grand_total' => $subtotal + $shipping,
            'status' => OrderStatus::PendingPayment,
            'note' => null,
            'placed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::PendingPayment,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Paid,
        ]);
    }
}
