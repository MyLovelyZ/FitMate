<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => null,
            'code' => Str::upper(fake()->unique()->bothify('FITMATE##??')),
            'name' => 'Diskon '.fake()->word(),
            'description' => null,
            'type' => CouponType::Percentage,
            'value' => 10,
            'min_purchase' => 100000,
            'max_discount' => 50000,
            'usage_limit' => 100,
            'starts_at' => now()->subDay(),
            'expiry_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ];
    }

    public function fixed(float $value): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CouponType::Fixed,
            'value' => $value,
            'max_discount' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expiry_date' => now()->subDay()->toDateString(),
        ]);
    }
}
