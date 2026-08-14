<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => PaymentMethod::BankTransfer,
            'provider' => null,
            'reference' => null,
            'amount' => fake()->numberBetween(50, 500) * 1000,
            'status' => PaymentStatus::Pending,
            'paid_at' => null,
            'expires_at' => now()->addDay(),
            'payload' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
