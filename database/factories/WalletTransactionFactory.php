<?php

namespace Database\Factories;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(100, 1500) * 1000;

        return [
            'wallet_id' => Wallet::factory(),
            'type' => 'escrow_release',
            'direction' => 'credit',
            'amount' => $amount,
            'balance_after' => $amount,
            'idempotency_key' => fake()->unique()->uuid(),
            'description' => 'Pencairan dana escrow',
        ];
    }

    public function debit(string $type = 'payout'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'direction' => 'debit',
        ]);
    }
}
