<?php

namespace Database\Seeders;

use App\Models\Size;
use App\Models\SizeGuide;
use Illuminate\Database\Seeder;

class SizeGuideSeeder extends Seeder
{
    /**
     * Rentang ukuran badan per label. Ini yang dipakai buat nentuin "kamu ukuran L".
     * Butuh SizeSeeder jalan duluan.
     *
     * measurement_key sengaja disamakan dengan nama kolom di user_body_profiles
     * (chest, waist, hip, foot_length) biar pencocokannya ngk butuh tabel pemetaan.
     * 'length' ngk ada di user_body_profiles, itu ukuran bajunya bukan ukuran badan.
     *
     * @var array<string, array{label: string, unit: string, sort_order: int}>
     */
    private const MEASUREMENTS = [
        'chest' => ['label' => 'Lingkar Dada', 'unit' => 'cm', 'sort_order' => 1],
        'length' => ['label' => 'Panjang Baju', 'unit' => 'cm', 'sort_order' => 2],
        'waist' => ['label' => 'Lingkar Pinggang', 'unit' => 'cm', 'sort_order' => 1],
        'hip' => ['label' => 'Lingkar Pinggul', 'unit' => 'cm', 'sort_order' => 2],
        'foot_length' => ['label' => 'Panjang Telapak Kaki', 'unit' => 'cm', 'sort_order' => 1],
    ];

    public function run(): void
    {
        // [size_type][nama ukuran][measurement_key] => [min, max]
        $ranges = [
            'top' => [
                'XS' => ['chest' => [86, 90], 'length' => [62, 64]],
                'S' => ['chest' => [90, 95], 'length' => [64, 67]],
                'M' => ['chest' => [95, 100], 'length' => [67, 70]],
                'L' => ['chest' => [100, 105], 'length' => [70, 72]],
                'XL' => ['chest' => [105, 110], 'length' => [72, 74]],
                'XXL' => ['chest' => [110, 116], 'length' => [74, 77]],
            ],
            'bottom' => [
                '28' => ['waist' => [71, 73.5], 'hip' => [88, 91]],
                '29' => ['waist' => [73.5, 76], 'hip' => [91, 94]],
                '30' => ['waist' => [76, 78.5], 'hip' => [94, 96.5]],
                '31' => ['waist' => [78.5, 81], 'hip' => [96.5, 99]],
                '32' => ['waist' => [81, 83.5], 'hip' => [99, 102]],
                '33' => ['waist' => [83.5, 86], 'hip' => [102, 104.5]],
                '34' => ['waist' => [86, 89], 'hip' => [104.5, 107]],
            ],
            'footwear' => [
                '38' => ['foot_length' => [23.5, 24.2]],
                '39' => ['foot_length' => [24.2, 24.8]],
                '40' => ['foot_length' => [24.8, 25.5]],
                '41' => ['foot_length' => [25.5, 26.2]],
                '42' => ['foot_length' => [26.2, 26.8]],
                '43' => ['foot_length' => [26.8, 27.5]],
                '44' => ['foot_length' => [27.5, 28.2]],
            ],
        ];

        foreach ($ranges as $sizeType => $sizeNames) {
            foreach ($sizeNames as $sizeName => $measurements) {
                $size = Size::where('size_type', $sizeType)->where('name', $sizeName)->first();

                if ($size === null) {
                    continue;
                }

                foreach ($measurements as $key => [$min, $max]) {
                    SizeGuide::updateOrCreate(
                        ['size_id' => $size->id, 'measurement_key' => $key],
                        [
                            'label' => self::MEASUREMENTS[$key]['label'],
                            'unit' => self::MEASUREMENTS[$key]['unit'],
                            'min_value' => $min,
                            'max_value' => $max,
                            'sort_order' => self::MEASUREMENTS[$key]['sort_order'],
                        ]
                    );
                }
            }
        }
    }
}
