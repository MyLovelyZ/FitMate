<?php

namespace Database\Factories;

use App\Models\BodyMeasurement;
use App\Models\SizeChartEntry;
use App\Models\SizeChartEntryMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SizeChartEntryMeasurement>
 */
class SizeChartEntryMeasurementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $min = fake()->randomFloat(2, 50, 100);

        return [
            'size_chart_entry_id' => SizeChartEntry::factory(),
            'body_measurement_id' => BodyMeasurement::factory(),
            'min_value' => $min,
            'max_value' => $min + 4,
        ];
    }

    public function range(float $min, float $max): static
    {
        return $this->state(fn (array $attributes): array => [
            'min_value' => $min,
            'max_value' => $max,
        ]);
    }
}
