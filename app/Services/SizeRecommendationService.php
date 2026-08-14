<?php

namespace App\Services;

use App\Enums\FitStatus;
use App\Enums\SizeType;
use App\Models\BodyMeasurement;
use App\Models\Product;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartEntryMeasurement;
use App\Models\SizeRecommendation;
use App\Models\UserBodyProfile;
use Illuminate\Support\Collection;

/**
 * Mesin rekomendasi ukuran FitMate.
 *
 * Karena ukuran badan pengguna dan rentang standar memakai kamus dimensi yang
 * sama (`body_measurements`), perbandingannya cukup mencocokkan angka per
 * dimensi terhadap rentang tiap label ukuran.
 *
 * Kasus yang ditangani dan hasilnya:
 * - produk kategori `none` / tanpa chart  → null, tidak ada perhitungan
 * - pengguna belum mengisi ukuran         → null
 * - baru mengisi sebagian dimensi         → dihitung dari dimensi yang ada, `fit_score` ikut turun
 * - tidak ada label yang cocok            → hasil dengan `entry` null dan skor 0
 */
class SizeRecommendationService
{
    /**
     * Rekomendasi ukuran untuk satu produk.
     */
    public function forProduct(UserBodyProfile $profile, Product $product): ?SizeRecommendationResult
    {
        $chart = $this->resolveChartFor($product, $profile);

        if (! $chart instanceof SizeChart) {
            return null;
        }

        return $this->compute($profile, $chart, $product);
    }

    /**
     * Rekomendasi umum per chart, tanpa terikat produk tertentu.
     */
    public function forChart(UserBodyProfile $profile, SizeChart $chart): ?SizeRecommendationResult
    {
        return $this->compute($profile, $chart, null);
    }

    /**
     * Chart yang berlaku untuk sebuah produk.
     *
     * Chart yang dipasang seller diutamakan. Kalau produk belum punya chart,
     * dicarikan dari `size_type` kategorinya — kategori `none` (aksesoris)
     * berhenti di sini dan tidak pernah dihitung.
     */
    public function resolveChartFor(Product $product, ?UserBodyProfile $profile = null): ?SizeChart
    {
        if ($product->size_chart_id !== null) {
            return $product->sizeChart;
        }

        $sizeType = $product->category?->size_type;

        if (! $sizeType instanceof SizeType || $sizeType === SizeType::None) {
            return null;
        }

        return SizeChart::query()
            ->active()
            ->matching($sizeType, $profile?->gender)
            ->first();
    }

    /**
     * Simpan hasil ke `size_recommendations` mengikuti unique key-nya,
     * supaya halaman produk tidak menghitung ulang tiap kali dibuka.
     */
    public function remember(UserBodyProfile $profile, SizeRecommendationResult $result, ?Product $product = null): SizeRecommendation
    {
        return SizeRecommendation::updateOrCreate(
            [
                'user_body_profile_id' => $profile->id,
                'size_chart_id' => $result->chart->id,
                'product_id' => $product?->id,
            ],
            [
                'size_chart_entry_id' => $result->entry?->id,
                'fit_status' => $result->fitStatus,
                'fit_score' => $result->fitScore,
                'matched_measurements' => $result->matchedMeasurements,
                'total_measurements' => $result->totalMeasurements,
                'computed_at' => now(),
            ],
        );
    }

    /**
     * Buang seluruh hasil hitung milik satu profil.
     *
     * Wajib dipanggil setiap kali ukuran badan pengguna berubah — kalau tidak,
     * pengguna melihat rekomendasi basi selamanya.
     */
    public function invalidateFor(UserBodyProfile $profile): void
    {
        $profile->sizeRecommendations()->delete();
    }

    private function compute(UserBodyProfile $profile, SizeChart $chart, ?Product $product): ?SizeRecommendationResult
    {
        $userValues = $profile->loadMissing('measurements')->measurementValues();

        if ($userValues === []) {
            return null;
        }

        $entries = $chart->entries()->where('is_active', true)->with('measurements.bodyMeasurement')->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $scored = $entries
            ->map(fn (SizeChartEntry $entry): array => $this->scoreEntry($entry, $userValues))
            ->filter(fn (array $candidate): bool => $candidate['total'] > 0)
            ->values();

        if ($scored->isEmpty()) {
            return null;
        }

        $best = $this->pickBest($scored);

        $result = new SizeRecommendationResult(
            chart: $chart,
            entry: $best['matched'] > 0 ? $best['entry'] : null,
            fitStatus: $this->deriveFitStatus($best['comparisons']),
            fitScore: round($best['matched'] / $best['total'] * 100, 2),
            matchedMeasurements: $best['matched'],
            totalMeasurements: $best['total'],
            comparisons: $best['comparisons'],
        );

        $this->remember($profile, $result, $product);

        return $result;
    }

    /**
     * Bandingkan badan pengguna dengan satu label ukuran.
     *
     * Dimensi yang belum diisi pengguna dilewati, tidak dihitung sebagai gagal —
     * itu sebabnya `total` diambil dari dimensi yang benar-benar bisa dibandingkan.
     *
     * @param  array<int, float>  $userValues
     * @return array{entry: SizeChartEntry, matched: int, total: int, deviation: float, comparisons: list<MeasurementComparison>}
     */
    private function scoreEntry(SizeChartEntry $entry, array $userValues): array
    {
        $comparisons = [];
        $matched = 0;
        $deviation = 0.0;

        foreach ($entry->measurements as $range) {
            $measurementId = $range->body_measurement_id;

            if (! array_key_exists($measurementId, $userValues)) {
                continue;
            }

            $userValue = $userValues[$measurementId];
            $comparison = $this->compare($range, $userValue);

            $comparisons[] = $comparison;

            if ($comparison->matches()) {
                $matched++;

                continue;
            }

            $deviation += $comparison->status === FitStatus::Tight
                ? (float) $range->min_value - $userValue
                : $userValue - (float) $range->max_value;
        }

        return [
            'entry' => $entry,
            'matched' => $matched,
            'total' => count($comparisons),
            'deviation' => $deviation,
            'comparisons' => $comparisons,
        ];
    }

    private function compare(SizeChartEntryMeasurement $range, float $userValue): MeasurementComparison
    {
        $min = (float) $range->min_value;
        $max = (float) $range->max_value;

        $status = match (true) {
            $userValue < $min => FitStatus::Tight,
            $userValue > $max => FitStatus::Loose,
            default => FitStatus::Fit,
        };

        return new MeasurementComparison(
            measurement: $range->bodyMeasurement,
            userValue: $userValue,
            minValue: $min,
            maxValue: $max,
            status: $status,
        );
    }

    /**
     * Label terbaik: paling banyak dimensi yang cocok. Kalau seri, ambil yang
     * simpangannya paling kecil, lalu yang `sort_order`-nya lebih kecil supaya
     * hasilnya konsisten dan tidak berubah-ubah antar pemanggilan.
     *
     * @param  Collection<int, array{entry: SizeChartEntry, matched: int, total: int, deviation: float, comparisons: list<MeasurementComparison>}>  $scored
     * @return array{entry: SizeChartEntry, matched: int, total: int, deviation: float, comparisons: list<MeasurementComparison>}
     */
    private function pickBest(Collection $scored): array
    {
        return $scored
            ->sort(fn (array $a, array $b): int => [$b['matched'], $a['deviation'], $a['entry']->sort_order]
                <=> [$a['matched'], $b['deviation'], $b['entry']->sort_order])
            ->first();
    }

    /**
     * Status keseluruhan diturunkan dari arah simpangan dimensi yang meleset.
     * Kalau melesetnya ke dua arah sekaligus, tidak ada satu arah yang jujur
     * bisa disebut — pakai `fit` dan biarkan rincian per dimensi yang bicara.
     *
     * @param  list<MeasurementComparison>  $comparisons
     */
    private function deriveFitStatus(array $comparisons): FitStatus
    {
        $tight = 0;
        $loose = 0;

        foreach ($comparisons as $comparison) {
            match ($comparison->status) {
                FitStatus::Tight => $tight++,
                FitStatus::Loose => $loose++,
                FitStatus::Fit => null,
            };
        }

        return match (true) {
            $tight === 0 && $loose === 0 => FitStatus::Fit,
            $tight > $loose => FitStatus::Tight,
            $loose > $tight => FitStatus::Loose,
            default => FitStatus::Fit,
        };
    }

    /**
     * Dimensi yang perlu diisi pengguna untuk satu jenis ukuran.
     *
     * @return Collection<int, BodyMeasurement>
     */
    public function requiredMeasurementsFor(SizeType $sizeType): Collection
    {
        return BodyMeasurement::query()->active()->forSizeType($sizeType)->get();
    }
}
