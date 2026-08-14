<?php

namespace Database\Factories;

use App\Enums\StoreOrderStatus;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreOrder>
 */
class StoreOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(50, 500) * 1000;
        $shipping = 20000;

        return [
            'order_id' => Order::factory(),
            'store_id' => Store::factory()->active(),
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'discount' => 0,
            'total' => $subtotal + $shipping,
            'status' => StoreOrderStatus::Pending,
            'note' => null,
        ];
    }

    public function status(StoreOrderStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }
}
