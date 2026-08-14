<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SizeChart;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'store_id' => Store::factory()->active(),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'size_chart_id' => SizeChart::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'target_gender' => Gender::Unisex,
            'base_price' => fake()->numberBetween(50, 500) * 1000,
            'weight_gram' => fake()->numberBetween(200, 1500),
            'status' => ProductStatus::Draft,
            'is_featured' => false,
            'published_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductStatus::PendingReview,
        ]);
    }

    /**
     * Produk aksesoris: tidak terikat chart mana pun, jadi tidak dihitung ukurannya.
     */
    public function withoutSizing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'size_chart_id' => null,
            'category_id' => Category::factory()->withoutSizing(),
        ]);
    }
}
