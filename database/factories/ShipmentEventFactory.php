<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentEvent>
 */
class ShipmentEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'status' => 'in_transit',
            'description' => 'Paket dalam perjalanan.',
            'location' => fake()->city(),
            'recorded_by' => null,
        ];
    }
}
