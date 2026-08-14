<?php

namespace Database\Factories;

use App\Enums\MeasurementScope;
use App\Models\BodyMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BodyMeasurement>
 */
class BodyMeasurementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return [
            'key' => Str::slug($label, '_'),
            'label' => Str::title($label),
            'description' => fake()->sentence(),
            'unit' => 'cm',
            'applies_to' => MeasurementScope::General,
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }

    public function scope(MeasurementScope $scope): static
    {
        return $this->state(fn (array $attributes): array => [
            'applies_to' => $scope,
        ]);
    }
}
