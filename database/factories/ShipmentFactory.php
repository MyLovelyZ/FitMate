<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\StoreOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_order_id' => StoreOrder::factory(),
            'courier' => fake()->randomElement(['jne', 'jnt', 'sicepat', 'anteraja']),
            'service' => fake()->randomElement(['reg', 'yes', 'cargo']),
            'tracking_number' => strtoupper(fake()->unique()->bothify('??########')),
            'cost' => fake()->numberBetween(15, 45) * 1000,
            'weight_gram' => fake()->numberBetween(300, 2500),
            'status' => 'pending',
        ];
    }

    public function inTransit(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_transit',
            'shipped_at' => now()->subDays(2),
        ]);
    }

    /**
     * Status inilah yang menyalakan hitungan mundur auto-release escrow.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'shipped_at' => now()->subDays(3),
            'delivered_at' => now()->subDay(),
        ]);
    }
}
