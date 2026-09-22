<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Default-nya QRIS yang belum dibayar: pembeli sudah dapat kode QR-nya tapi
     * uangnya belum masuk.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => 'qris',
            'provider' => 'midtrans',
            'reference' => 'TRX-'.fake()->unique()->numerify('##########'),
            'qr_string' => '00020101021226'.fake()->numerify('##############'),
            'qr_image_url' => null,
            'amount' => fake()->numberBetween(100, 2000) * 1000,
            'provider_fee' => 0,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ];
    }

    public function virtualAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => 'virtual_account',
            'qr_string' => null,
            'va_bank' => fake()->randomElement(['bca', 'bni', 'mandiri']),
            'va_number' => fake()->numerify('############'),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
            'expires_at' => null,
            'provider_fee' => round(($attributes['amount'] ?? 0) * 0.007, 2),
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
