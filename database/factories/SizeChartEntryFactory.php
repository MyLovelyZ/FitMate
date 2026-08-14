<?php

namespace Database\Factories;

use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SizeChartEntry>
 */
class SizeChartEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'size_chart_id' => SizeChart::factory(),
            'label' => fake()->unique()->randomElement(['XS', 'S', 'M', 'L', 'XL', 'XXL']),
            'sort_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }

    public function label(string $label, int $sortOrder): static
    {
        return $this->state(fn (array $attributes): array => [
            'label' => $label,
            'sort_order' => $sortOrder,
        ]);
    }
}
