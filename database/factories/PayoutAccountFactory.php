<?php

namespace Database\Factories;

use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutAccount>
 */
class PayoutAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->seller(),
            'type' => 'bank',
            'bank_code' => fake()->randomElement(['bca', 'bni', 'mandiri', 'bri']),
            'account_name' => fake()->name(),
            'account_number' => fake()->numerify('##########'),
            'is_default' => true,
            'verified_at' => now(),
        ];
    }

    public function ewallet(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'ewallet',
            'bank_code' => fake()->randomElement(['gopay', 'ovo', 'dana']),
            'account_number' => fake()->numerify('08##########'),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => null,
        ]);
    }
}
