<?php

namespace Database\Factories;

use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * Default-nya voucher persentase 10% tanpa batas, aktif, tanpa minimal
     * belanja — paling mudah dipakai di test.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PROMO###??'),
            'name' => 'Diskon '.fake()->words(2, true),
            'type' => 'percentage',
            'value' => 10,
            'max_discount' => null,
            'min_subtotal' => 0,
            'usage_limit' => null,
            'per_user_limit' => null,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    public function percentage(float $percent, ?float $maxDiscount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'value' => $percent,
            'max_discount' => $maxDiscount,
        ]);
    }

    public function fixed(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'value' => $amount,
            'max_discount' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->addWeek(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
