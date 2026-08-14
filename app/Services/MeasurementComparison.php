<?php

namespace App\Services;

use App\Enums\FitStatus;
use App\Models\BodyMeasurement;

/**
 * Hasil perbandingan satu dimensi ukur: nilai badan pengguna versus rentang
 * standar FitMate untuk satu label ukuran.
 *
 * Ini yang dipakai halaman produk untuk menjelaskan *kenapa* sebuah ukuran
 * direkomendasikan, bukan sekadar menampilkan hurufnya.
 */
final readonly class MeasurementComparison
{
    public function __construct(
        public BodyMeasurement $measurement,
        public float $userValue,
        public float $minValue,
        public float $maxValue,
        public FitStatus $status,
    ) {}

    public function matches(): bool
    {
        return $this->status === FitStatus::Fit;
    }

    /**
     * Penjelasan singkat untuk ditampilkan ke pembeli.
     */
    public function explanation(): string
    {
        $unit = $this->measurement->unit;
        $range = "{$this->formatted($this->minValue)}–{$this->formatted($this->maxValue)} {$unit}";

        return match ($this->status) {
            FitStatus::Fit => "{$this->formatted($this->userValue)} {$unit} — masuk rentang {$range}",
            FitStatus::Tight => "{$this->formatted($this->userValue)} {$unit} — di bawah rentang {$range}, cenderung sempit",
            FitStatus::Loose => "{$this->formatted($this->userValue)} {$unit} — di atas rentang {$range}, cenderung longgar",
        };
    }

    private function formatted(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    }
}
