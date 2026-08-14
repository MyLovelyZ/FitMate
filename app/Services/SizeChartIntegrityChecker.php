<?php

namespace App\Services;

use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartEntryMeasurement;

/**
 * BE-035: memeriksa kesehatan rentang sebuah chart.
 *
 * Dua masalah yang dicari, keduanya tidak bisa dicegah skema:
 *
 * - **Celah.** L berhenti di 103 tapi XL mulai dari 105 — orang berdada 104
 *   tidak akan pernah dapat rekomendasi.
 * - **Tumpang tindih berlebihan.** Dua ukuran menutupi rentang yang hampir sama,
 *   sehingga pilihan sistem jadi sewenang-wenang.
 *
 * Hasilnya peringatan, bukan penolakan: kadang celah kecil memang disengaja,
 * dan yang perlu dilakukan sistem adalah memastikan admin menyadarinya.
 */
class SizeChartIntegrityChecker
{
    /**
     * @return list<string>
     */
    public function warningsFor(SizeChart $chart): array
    {
        $chart->loadMissing('entries.measurements.bodyMeasurement');

        $warnings = [];

        if ($chart->entries->isEmpty()) {
            return ['Tabel ini belum punya satu pun label ukuran.'];
        }

        foreach ($this->rangesByMeasurement($chart) as $measurementKey => $ranges) {
            $warnings = [...$warnings, ...$this->inspect($measurementKey, $ranges)];
        }

        foreach ($chart->entries as $entry) {
            if ($entry->measurements->isEmpty()) {
                $warnings[] = "Ukuran {$entry->label} belum punya rentang sama sekali, jadi tidak akan pernah cocok dengan siapa pun.";
            }
        }

        return $warnings;
    }

    /**
     * Kelompokkan rentang per dimensi, urut mengikuti `sort_order` entry.
     *
     * @return array<string, list<array{label: string, min: float, max: float, unit: string}>>
     */
    private function rangesByMeasurement(SizeChart $chart): array
    {
        $grouped = [];

        foreach ($chart->entries->sortBy('sort_order') as $entry) {
            /** @var SizeChartEntry $entry */
            foreach ($entry->measurements as $range) {
                /** @var SizeChartEntryMeasurement $range */
                $grouped[$range->bodyMeasurement->label][] = [
                    'label' => $entry->label,
                    'min' => (float) $range->min_value,
                    'max' => (float) $range->max_value,
                    'unit' => $range->bodyMeasurement->unit,
                ];
            }
        }

        return $grouped;
    }

    /**
     * @param  list<array{label: string, min: float, max: float, unit: string}>  $ranges
     * @return list<string>
     */
    private function inspect(string $measurementLabel, array $ranges): array
    {
        $warnings = [];

        foreach ($ranges as $range) {
            if ($range['max'] < $range['min']) {
                $warnings[] = "{$measurementLabel} pada ukuran {$range['label']}: nilai maksimum lebih kecil dari minimum.";
            }
        }

        for ($i = 0; $i < count($ranges) - 1; $i++) {
            $current = $ranges[$i];
            $next = $ranges[$i + 1];

            if ($next['min'] > $current['max']) {
                $gap = round($next['min'] - $current['max'], 2);
                $warnings[] = sprintf(
                    '%s: ada celah %s %s antara ukuran %s (sampai %s) dan %s (mulai %s). Orang di rentang itu tidak dapat rekomendasi.',
                    $measurementLabel,
                    $gap,
                    $current['unit'],
                    $current['label'],
                    $current['max'],
                    $next['label'],
                    $next['min'],
                );

                continue;
            }

            // Bersentuhan di satu titik batas itu normal dan memang disengaja;
            // yang dilaporkan hanya tumpang tindih yang benar-benar lebar.
            $overlap = $current['max'] - $next['min'];
            $span = max($current['max'] - $current['min'], 0.01);

            if ($overlap > 0 && $overlap / $span > 0.5) {
                $warnings[] = sprintf(
                    '%s: ukuran %s dan %s tumpang tindih terlalu lebar (%s %s). Pilihan sistem jadi kurang tegas.',
                    $measurementLabel,
                    $current['label'],
                    $next['label'],
                    round($overlap, 2),
                    $current['unit'],
                );
            }
        }

        return $warnings;
    }
}
