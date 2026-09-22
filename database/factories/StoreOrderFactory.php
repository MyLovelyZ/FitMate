<?php

namespace Database\Factories;

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
     * Komisi platform dihitung di sini dan disimpan sebagai angka, bukan dihitung
     * ulang saat dibaca — persis seperti yang dilakukan checkout sungguhan.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(100, 1500) * 1000;
        $shipping = fake()->numberBetween(15, 45) * 1000;
        $total = $subtotal + $shipping;
        $feeRate = 0.05;
        $fee = round($total * $feeRate, 2);

        return [
            'order_id' => Order::factory(),
            'store_id' => Store::factory(),
            'store_order_number' => 'SO-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'discount' => 0,
            'total' => $total,
            'platform_fee_rate' => $feeRate,
            'platform_fee_amount' => $fee,
            'seller_earning' => $total - $fee,
            'status' => 'pending',
        ];
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'shipped',
            'shipped_at' => now()->subDays(3),
        ]);
    }

    /**
     * Barang sampai tapi pembeli belum menekan "diterima". Di sinilah escrow
     * paling sering menunggu.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'shipped_at' => now()->subDays(3),
            'delivered_at' => now()->subDay(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'shipped_at' => now()->subDays(9),
            'delivered_at' => now()->subDays(6),
            'buyer_confirmed_at' => now()->subDays(5),
            'completed_at' => now()->subDays(5),
        ]);
    }

    public function disputed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'disputed',
            'shipped_at' => now()->subDays(5),
            'delivered_at' => now()->subDays(2),
        ]);
    }
}
