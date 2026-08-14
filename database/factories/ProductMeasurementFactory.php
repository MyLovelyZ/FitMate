<?php

namespace Database\Factories;

use App\Models\BodyMeasurement;
use App\Models\ProductMeasurement;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMeasurement>
 */
class ProductMeasurementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'body_measurement_id' => BodyMeasurement::factory(),
            'value' => fake()->randomFloat(2, 20, 130),
        ];
    }
}
