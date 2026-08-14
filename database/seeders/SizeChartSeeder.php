<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\SizeType;
use App\Models\BodyMeasurement;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartEntryMeasurement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Standar ukuran resmi FitMate — seluruh akurasi rekomendasi bergantung ke sini.
 *
 * ANGKANYA MASIH PROVISIONAL. Rentang di bawah disusun mengacu ke ukuran tubuh
 * dewasa Asia Tenggara dan pola konveksi lokal, dipakai supaya fitur bisa jalan
 * dan didemokan. KEP-1 belum diputuskan tim, jadi sebelum rilis produksi angka
 * ini wajib ditinjau ulang bersama (acuan SNI atau data antropometri Indonesia).
 *
 * Aturan penyusunan yang dipakai, jaga saat menyunting:
 * - Rentang antar entry bersambung (max entry sebelumnya = min entry berikutnya),
 *   supaya tidak ada orang yang jatuh di celah dan tidak dapat rekomendasi.
 * - `sort_order` naik dari ukuran terkecil ke terbesar.
 */
class SizeChartSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->charts() as $definition) {
            $chart = SizeChart::updateOrCreate(
                ['size_type' => $definition['size_type'], 'gender' => $definition['gender']],
                [
                    'name' => $definition['name'],
                    'slug' => Str::slug($definition['name']),
                    'description' => $definition['description'],
                    'is_active' => true,
                ],
            );

            foreach (array_values($definition['entries']) as $index => $entry) {
                $chartEntry = SizeChartEntry::updateOrCreate(
                    ['size_chart_id' => $chart->id, 'label' => $entry['label']],
                    ['sort_order' => $index + 1, 'is_active' => true],
                );

                foreach ($entry['ranges'] as $key => $range) {
                    SizeChartEntryMeasurement::updateOrCreate(
                        [
                            'size_chart_entry_id' => $chartEntry->id,
                            'body_measurement_id' => $this->measurementId($key),
                        ],
                        ['min_value' => $range[0], 'max_value' => $range[1]],
                    );
                }
            }
        }
    }

    private function measurementId(string $key): int
    {
        /** @var array<string, int> $cache */
        static $cache = [];

        return $cache[$key] ??= BodyMeasurement::where('key', $key)->value('id')
            ?? throw new \RuntimeException("Dimensi '{$key}' belum ada. Jalankan BodyMeasurementSeeder lebih dulu.");
    }

    /**
     * @return list<array{name: string, size_type: SizeType, gender: Gender, description: string, entries: list<array{label: string, ranges: array<string, array{0: float, 1: float}>}>}>
     */
    private function charts(): array
    {
        return [
            [
                'name' => 'Atasan Pria',
                'size_type' => SizeType::Top,
                'gender' => Gender::Male,
                'description' => 'Standar FitMate untuk kaos, kemeja, dan jaket pria dewasa.',
                'entries' => [
                    ['label' => 'S', 'ranges' => ['lingkar_dada' => [88, 92], 'lebar_bahu' => [41, 43], 'panjang_badan' => [66, 68], 'tinggi_badan' => [158, 165]]],
                    ['label' => 'M', 'ranges' => ['lingkar_dada' => [92, 97], 'lebar_bahu' => [43, 45], 'panjang_badan' => [68, 70], 'tinggi_badan' => [165, 170]]],
                    ['label' => 'L', 'ranges' => ['lingkar_dada' => [97, 102], 'lebar_bahu' => [45, 47], 'panjang_badan' => [70, 72], 'tinggi_badan' => [170, 175]]],
                    ['label' => 'XL', 'ranges' => ['lingkar_dada' => [102, 107], 'lebar_bahu' => [47, 49], 'panjang_badan' => [72, 74], 'tinggi_badan' => [175, 180]]],
                    ['label' => 'XXL', 'ranges' => ['lingkar_dada' => [107, 113], 'lebar_bahu' => [49, 52], 'panjang_badan' => [74, 77], 'tinggi_badan' => [180, 190]]],
                ],
            ],
            [
                'name' => 'Atasan Wanita',
                'size_type' => SizeType::Top,
                'gender' => Gender::Female,
                'description' => 'Standar FitMate untuk atasan wanita dewasa.',
                'entries' => [
                    ['label' => 'S', 'ranges' => ['lingkar_dada' => [82, 86], 'lebar_bahu' => [36, 38], 'panjang_badan' => [58, 60], 'tinggi_badan' => [148, 155]]],
                    ['label' => 'M', 'ranges' => ['lingkar_dada' => [86, 90], 'lebar_bahu' => [38, 40], 'panjang_badan' => [60, 62], 'tinggi_badan' => [155, 160]]],
                    ['label' => 'L', 'ranges' => ['lingkar_dada' => [90, 95], 'lebar_bahu' => [40, 42], 'panjang_badan' => [62, 64], 'tinggi_badan' => [160, 165]]],
                    ['label' => 'XL', 'ranges' => ['lingkar_dada' => [95, 100], 'lebar_bahu' => [42, 44], 'panjang_badan' => [64, 66], 'tinggi_badan' => [165, 170]]],
                    ['label' => 'XXL', 'ranges' => ['lingkar_dada' => [100, 106], 'lebar_bahu' => [44, 46], 'panjang_badan' => [66, 69], 'tinggi_badan' => [170, 180]]],
                ],
            ],
            [
                'name' => 'Bawahan Pria',
                'size_type' => SizeType::Bottom,
                'gender' => Gender::Male,
                'description' => 'Standar FitMate untuk celana pria dewasa.',
                'entries' => [
                    ['label' => 'S', 'ranges' => ['lingkar_pinggang' => [71, 76], 'lingkar_pinggul' => [88, 93], 'panjang_kaki' => [96, 99], 'tinggi_badan' => [158, 165]]],
                    ['label' => 'M', 'ranges' => ['lingkar_pinggang' => [76, 81], 'lingkar_pinggul' => [93, 98], 'panjang_kaki' => [99, 102], 'tinggi_badan' => [165, 170]]],
                    ['label' => 'L', 'ranges' => ['lingkar_pinggang' => [81, 86], 'lingkar_pinggul' => [98, 103], 'panjang_kaki' => [102, 105], 'tinggi_badan' => [170, 175]]],
                    ['label' => 'XL', 'ranges' => ['lingkar_pinggang' => [86, 91], 'lingkar_pinggul' => [103, 108], 'panjang_kaki' => [105, 108], 'tinggi_badan' => [175, 180]]],
                    ['label' => 'XXL', 'ranges' => ['lingkar_pinggang' => [91, 97], 'lingkar_pinggul' => [108, 114], 'panjang_kaki' => [108, 111], 'tinggi_badan' => [180, 190]]],
                ],
            ],
            [
                'name' => 'Bawahan Wanita',
                'size_type' => SizeType::Bottom,
                'gender' => Gender::Female,
                'description' => 'Standar FitMate untuk celana dan rok wanita dewasa.',
                'entries' => [
                    ['label' => 'S', 'ranges' => ['lingkar_pinggang' => [63, 68], 'lingkar_pinggul' => [86, 91], 'panjang_kaki' => [92, 95], 'tinggi_badan' => [148, 155]]],
                    ['label' => 'M', 'ranges' => ['lingkar_pinggang' => [68, 73], 'lingkar_pinggul' => [91, 96], 'panjang_kaki' => [95, 98], 'tinggi_badan' => [155, 160]]],
                    ['label' => 'L', 'ranges' => ['lingkar_pinggang' => [73, 78], 'lingkar_pinggul' => [96, 101], 'panjang_kaki' => [98, 101], 'tinggi_badan' => [160, 165]]],
                    ['label' => 'XL', 'ranges' => ['lingkar_pinggang' => [78, 84], 'lingkar_pinggul' => [101, 107], 'panjang_kaki' => [101, 104], 'tinggi_badan' => [165, 170]]],
                    ['label' => 'XXL', 'ranges' => ['lingkar_pinggang' => [84, 90], 'lingkar_pinggul' => [107, 113], 'panjang_kaki' => [104, 107], 'tinggi_badan' => [170, 180]]],
                ],
            ],
            [
                'name' => 'Alas Kaki Unisex',
                'size_type' => SizeType::Footwear,
                'gender' => Gender::Unisex,
                'description' => 'Standar FitMate untuk sepatu dan sandal, penomoran EU (KEP-2).',
                'entries' => [
                    ['label' => '38', 'ranges' => ['panjang_telapak_kaki' => [23.9, 24.6], 'lebar_telapak_kaki' => [8.8, 9.2]]],
                    ['label' => '39', 'ranges' => ['panjang_telapak_kaki' => [24.6, 25.3], 'lebar_telapak_kaki' => [9.0, 9.4]]],
                    ['label' => '40', 'ranges' => ['panjang_telapak_kaki' => [25.3, 25.9], 'lebar_telapak_kaki' => [9.2, 9.6]]],
                    ['label' => '41', 'ranges' => ['panjang_telapak_kaki' => [25.9, 26.6], 'lebar_telapak_kaki' => [9.4, 9.8]]],
                    ['label' => '42', 'ranges' => ['panjang_telapak_kaki' => [26.6, 27.2], 'lebar_telapak_kaki' => [9.6, 10.0]]],
                    ['label' => '43', 'ranges' => ['panjang_telapak_kaki' => [27.2, 27.9], 'lebar_telapak_kaki' => [9.8, 10.2]]],
                    ['label' => '44', 'ranges' => ['panjang_telapak_kaki' => [27.9, 28.5], 'lebar_telapak_kaki' => [10.0, 10.4]]],
                    ['label' => '45', 'ranges' => ['panjang_telapak_kaki' => [28.5, 29.2], 'lebar_telapak_kaki' => [10.2, 10.6]]],
                ],
            ],
        ];
    }
}
