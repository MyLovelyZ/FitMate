<?php

namespace Database\Factories;

use App\Enums\FitStatus;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeRecommendation;
use App\Models\UserBodyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SizeRecommendation>
 */
class SizeRecommendationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_body_profile_id' => UserBodyProfile::factory(),
            'size_chart_id' => SizeChart::factory(),
            'size_chart_entry_id' => SizeChartEntry::factory(),
            'product_id' => null,
            'fit_status' => FitStatus::Fit,
            'fit_score' => fake()->randomFloat(2, 0, 100),
            'matched_measurements' => 3,
            'total_measurements' => 3,
            'computed_at' => now(),
        ];
    }
}
