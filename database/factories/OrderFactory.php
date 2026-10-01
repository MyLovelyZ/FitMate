<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Default-nya order yang baru dibuat dan menunggu dibayar — keadaan pertama
     * yang dilewati setiap order.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(100, 2000) * 1000;
        $shipping = fake()->numberBetween(15, 45) * 1000;

        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'recipient_name' => fake()->name(),
            'recipient_phone' => fake()->numerify('08##########'),
            'shipping_street' => fake()->streetAddress(),
            'shipping_city' => fake()->city(),
            'shipping_province' => fake()->state(),
            'shipping_postal_code' => fake()->numerify('#####'),
            'shipping_country' => 'Indonesia',
            'subtotal' => $subtotal,
            'shipping_total' => $shipping,
            'discount_total' => 0,
            'grand_total' => $subtotal + $shipping,
            'status' => 'pending_payment',
            'placed_at' => now(),
            'paid_at' => null,
            'expires_at' => now()->addDay(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
            'expires_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'paid_at' => now()->subDays(10),
            'expires_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subHour(),
        ]);
    }
}
