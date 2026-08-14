<?php

namespace Database\Factories;

use App\Models\BodyMeasurement;
use App\Models\UserBodyMeasurement;
use App\Models\UserBodyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBodyMeasurement>
 */
class UserBodyMeasurementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_body_profile_id' => UserBodyProfile::factory(),
            'body_measurement_id' => BodyMeasurement::factory(),
            'value' => fake()->randomFloat(2, 20, 190),
        ];
    }
}
