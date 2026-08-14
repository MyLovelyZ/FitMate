<?php

namespace Database\Factories;

use App\Enums\BannerPlacement;
use App\Models\BannerPromo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BannerPromo>
 */
class BannerPromoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'subtitle' => fake()->sentence(6),
            'image' => 'banners/'.fake()->uuid().'.jpg',
            'link_url' => null,
            'placement' => BannerPlacement::HomeHero,
            'sort_order' => fake()->numberBetween(1, 10),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }
}
