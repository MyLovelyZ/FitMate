<?php

namespace Database\Factories;

use App\Models\Size;
use App\Models\SizeGuide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SizeGuide>
 */
class SizeGuideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $min = fake()->numberBetween(60, 110);

        return [
            'size_id' => Size::factory(),
            'measurement_key' => 'chest',
            'label' => 'Lingkar Dada',
            'unit' => 'cm',
            'min_value' => $min,
            'max_value' => $min + 4,
            'sort_order' => 1,
        ];
    }

    /**
     * Bikin baris buat dimensi tertentu, contoh: ->forMeasurement('waist', 'Lingkar Pinggang').
     * $key harus sama dengan nama kolom di user_body_profiles biar bisa dicocokkan.
     */
    public function forMeasurement(string $key, string $label, string $unit = 'cm'): static
    {
        return $this->state(fn (array $attributes) => [
            'measurement_key' => $key,
            'label' => $label,
            'unit' => $unit,
        ]);
    }
}
