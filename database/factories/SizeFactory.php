<?php

namespace Database\Factories;

use App\Models\CategoryType;
use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Size>
 */
class SizeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['XS', 'S', 'M', 'L', 'XL', 'XXL']);

        return [
            'category_type_id' => CategoryType::factory(),
            'size_type' => 'top',
            'name' => $name,
            'code' => $name,
            'sort_order' => fake()->numberBetween(1, 6),
            'is_active' => true,
        ];
    }

    public function footwear(): static
    {
        return $this->state(function (array $attributes) {
            $name = (string) fake()->unique()->numberBetween(36, 45);

            return [
                'size_type' => 'footwear',
                'name' => $name,
                'code' => $name,
            ];
        });
    }

    public function bottom(): static
    {
        return $this->state(fn (array $attributes) => [
            'size_type' => 'bottom',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
