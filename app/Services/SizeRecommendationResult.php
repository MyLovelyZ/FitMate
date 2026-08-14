<?php

namespace App\Services;

use App\Enums\FitStatus;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;

/**
 * Keluaran SizeRecommendationService.
 *
 * `entry` boleh null: artinya chart-nya ketemu tapi tidak ada satu pun label
 * ukuran yang cocok dengan badan pengguna. Itu informasi yang tetap berguna —
 * halaman produk menampilkannya sebagai "ukuranmu di luar rentang yang tersedia",
 * bukan menyembunyikannya.
 */
final readonly class SizeRecommendationResult
{
    /**
     * @param  list<MeasurementComparison>  $comparisons
     */
    public function __construct(
        public SizeChart $chart,
        public ?SizeChartEntry $entry,
        public FitStatus $fitStatus,
        public float $fitScore,
        public int $matchedMeasurements,
        public int $totalMeasurements,
        public array $comparisons = [],
    ) {}

    public function hasSuggestion(): bool
    {
        return $this->entry !== null;
    }

    public function label(): ?string
    {
        return $this->entry?->label;
    }

    /**
     * Skor rendah berarti hanya sedikit dimensi yang cocok — sampaikan ke
     * pengguna sebagai saran yang perlu dicek ulang, bukan sebagai kepastian.
     */
    public function isConfident(): bool
    {
        return $this->fitScore >= 60.0;
    }

    /**
     * Dimensi yang membuat ukuran ini tidak sepenuhnya pas.
     *
     * @return list<MeasurementComparison>
     */
    public function mismatches(): array
    {
        return array_values(array_filter(
            $this->comparisons,
            fn (MeasurementComparison $comparison): bool => ! $comparison->matches(),
        ));
    }
}
