<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\SizeType;
use App\Models\SizeChart;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SizeChart>
 */
class SizeChartFactory extends Factory
{
    /**
     * `size_charts` punya unique(size_type, gender), jadi kombinasinya tidak
     * boleh diacak. Default-nya alas kaki unisex; pakai state untuk yang lain.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->attributesFor(SizeType::Footwear, Gender::Unisex);
    }

    public function forTops(Gender $gender = Gender::Male): static
    {
        return $this->state(fn (array $attributes): array => $this->attributesFor(SizeType::Top, $gender));
    }

    public function forBottoms(Gender $gender = Gender::Male): static
    {
        return $this->state(fn (array $attributes): array => $this->attributesFor(SizeType::Bottom, $gender));
    }

    public function forFootwear(): static
    {
        return $this->state(fn (array $attributes): array => $this->attributesFor(SizeType::Footwear, Gender::Unisex));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFor(SizeType $sizeType, Gender $gender): array
    {
        $name = $sizeType->label().' '.$gender->label();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'size_type' => $sizeType,
            'gender' => $gender,
            'description' => null,
            'is_active' => true,
        ];
    }
}
