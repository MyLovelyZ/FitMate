<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->paid(),
            'refund_number' => 'RF-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'amount' => fake()->numberBetween(100, 1000) * 1000,
            'destination' => 'source',
            'status' => 'requested',
            'reason' => 'Barang tidak sesuai deskripsi.',
            'requested_by' => User::factory(),
        ];
    }

    public function toWallet(): static
    {
        return $this->state(fn (array $attributes) => [
            'destination' => 'wallet',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'approved_by' => User::factory()->admin(),
            'provider' => 'midtrans',
            'reference' => 'RFD-'.fake()->unique()->numerify('##########'),
            'completed_at' => now(),
        ]);
    }
}
