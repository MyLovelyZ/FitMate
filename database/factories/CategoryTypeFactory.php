<?php

namespace Database\Factories;

use App\Models\CategoryType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CategoryType>
 */
class CategoryTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'has_sizes' => true,
            'default_size_type' => fake()->randomElement(['top', 'bottom', 'footwear']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Jenis kategori yang produknya ngk punya ukuran, contoh: tas, topi.
     */
    public function withoutSizes(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_sizes' => false,
            'default_size_type' => 'none',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
