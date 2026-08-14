<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\User;
use App\Models\UserBodyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBodyProfile>
 */
class UserBodyProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'profile_name' => 'Profil Saya',
            'gender' => fake()->randomElement([Gender::Male, Gender::Female]),
            'is_default' => true,
        ];
    }

    public function forGender(Gender $gender): static
    {
        return $this->state(fn (array $attributes): array => [
            'gender' => $gender,
        ]);
    }
}
