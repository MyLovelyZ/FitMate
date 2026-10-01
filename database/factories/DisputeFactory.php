<?php

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispute>
 */
class DisputeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_order_id' => StoreOrder::factory()->delivered(),
            'opened_by' => User::factory(),
            'reason' => fake()->randomElement(['not_received', 'not_as_described', 'damaged', 'wrong_item']),
            'description' => fake()->sentence(12),
            'status' => 'open',
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'under_review',
        ]);
    }

    public function resolvedForBuyer(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved_buyer',
            'resolved_by' => User::factory()->admin(),
            'resolution_note' => 'Bukti pembeli diterima, dana dikembalikan.',
            'resolved_at' => now(),
        ]);
    }

    public function resolvedForSeller(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved_seller',
            'resolved_by' => User::factory()->admin(),
            'resolution_note' => 'Barang terbukti sesuai, dana dicairkan ke penjual.',
            'resolved_at' => now(),
        ]);
    }
}
