<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserBodyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBodyProfile>
 */
class UserBodyProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'profile_name' => 'Profil Saya',
            'gender' => fake()->randomElement(['male', 'female']),
            'is_default' => false,
            // Rentangnya sengaja dipaskan ke tabel di SizeGuideSeeder,
            // biar tiap profil hasil factory selalu dapat rekomendasi ukuran.
            'height' => fake()->randomFloat(1, 150, 185),
            'weight' => fake()->randomFloat(1, 45, 95),
            'chest' => fake()->randomFloat(1, 86, 115),
            'waist' => fake()->randomFloat(1, 71, 88),
            'hip' => fake()->randomFloat(1, 88, 106),
            'foot_length' => fake()->randomFloat(1, 23.5, 28),
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Profil yang ukurannya belum diisi, buat nguji state "belum isi ukuran".
     */
    public function withoutMeasurements(): static
    {
        return $this->state(fn (array $attributes) => [
            'height' => null,
            'weight' => null,
            'chest' => null,
            'waist' => null,
            'hip' => null,
            'foot_length' => null,
        ]);
    }
}
