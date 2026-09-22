<?php

namespace Database\Factories;

use App\Models\EscrowHold;
use App\Models\Payment;
use App\Models\StoreOrder;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EscrowHold>
 */
class EscrowHoldFactory extends Factory
{
    /**
     * Default-nya dana yang masih ditahan dan belum punya batas auto-release,
     * karena barangnya memang belum ditandai terkirim.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gross = fake()->numberBetween(100, 1500) * 1000;
        $fee = round($gross * 0.05, 2);

        return [
            'store_order_id' => StoreOrder::factory(),
            'payment_id' => Payment::factory()->paid(),
            'seller_wallet_id' => Wallet::factory(),
            'gross_amount' => $gross,
            'platform_fee_amount' => $fee,
            'net_amount' => $gross - $fee,
            'refunded_amount' => 0,
            'status' => 'held',
            'held_at' => now(),
            'auto_release_at' => null,
        ];
    }

    /**
     * Barang sudah sampai, hitungan mundur pencairan otomatis berjalan.
     */
    public function awaitingConfirmation(int $daysLeft = 6): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_release_at' => now()->addDays($daysLeft),
        ]);
    }

    /**
     * Batas waktunya sudah lewat — inilah yang dipungut scheduler auto-release.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_release_at' => now()->subDay(),
        ]);
    }

    public function released(string $reason = 'buyer_confirmed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'released',
            'release_reason' => $reason,
            'released_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'refunded_amount' => $attributes['gross_amount'] ?? 0,
            'refunded_at' => now(),
        ]);
    }
}
