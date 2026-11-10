<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatConversation>
 */
class ChatConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'assigned_to' => null,
            'status' => 'open',
            'last_message_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'closed', 'closed_at' => now()]);
    }
}
