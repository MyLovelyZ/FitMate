<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Enums\SizeType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'type' => CategoryType::Clothing,
            'size_type' => SizeType::Top,
            'description' => null,
            'icon' => null,
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }

    public function sizeType(SizeType $sizeType, CategoryType $type = CategoryType::Clothing): static
    {
        return $this->state(fn (array $attributes): array => [
            'size_type' => $sizeType,
            'type' => $type,
        ]);
    }

    /**
     * Kategori aksesoris — produk di bawahnya tidak ikut perhitungan ukuran.
     */
    public function withoutSizing(): static
    {
        return $this->sizeType(SizeType::None, CategoryType::Accessories);
    }
}
