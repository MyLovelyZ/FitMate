<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
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
            'courier' => fake()->randomElement(['jne', 'jnt', 'sicepat']),
            'service' => 'reg',
            'tracking_number' => null,
            'cost' => 20000,
            'weight_gram' => fake()->numberBetween(500, 3000),
            'status' => ShipmentStatus::Pending,
            'shipped_at' => null,
            'delivered_at' => null,
        ];
    }
}
