<?php

namespace Database\Factories;

use App\Enums\FitFeedback;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReview>
 */
class ProductReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'order_item_id' => null,
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->sentence(),
            'fit_feedback' => fake()->randomElement(FitFeedback::cases()),
            'size_purchased' => fake()->randomElement(['S', 'M', 'L', 'XL']),
        ];
    }
}
