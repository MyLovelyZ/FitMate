<?php

namespace Database\Factories;

use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(200, 3000) * 1000;
        $fee = 6500; // biaya transfer antar bank

        return [
            'user_id' => User::factory()->seller(),
            'wallet_id' => Wallet::factory(),
            'payout_account_id' => PayoutAccount::factory(),
            'payout_number' => 'PO-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'account_bank_code' => 'bca',
            'account_name' => fake()->name(),
            'account_number' => fake()->numerify('##########'),
            'amount' => $amount,
            'fee_amount' => $fee,
            'net_amount' => $amount - $fee,
            'status' => 'requested',
            'requested_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'provider' => 'xendit',
            'reference' => 'DSB-'.fake()->unique()->numerify('##########'),
            'processed_at' => now()->subMinutes(30),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failure_reason' => 'Nomor rekening tidak ditemukan.',
            'processed_at' => now(),
        ]);
    }
}
