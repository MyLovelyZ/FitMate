<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentEvent>
 */
class PaymentEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'provider' => 'midtrans',
            'event_id' => 'evt_'.fake()->unique()->lexify('????????????'),
            'event_type' => 'payment.settled',
            'signature' => fake()->sha256(),
            'payload' => ['transaction_status' => 'settlement'],
            'processed_at' => now(),
        ];
    }

    /**
     * Webhook yang sudah masuk tapi belum diproses — dipakai menguji pekerja ulang.
     */
    public function unprocessed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed_at' => null,
        ]);
    }
}
