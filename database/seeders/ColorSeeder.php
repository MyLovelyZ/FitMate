<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    /**
     * Palet warna resmi FitMate. Data referensi, sama seperti kategori dan ukuran,
     * jadi dipakai updateOrCreate dengan slug sebagai kunci alami.
     *
     * @var array<int, array{name: string, slug: string, hex_code: string}>
     */
    private const COLORS = [
        ['name' => 'Hitam', 'slug' => 'hitam', 'hex_code' => '#111111'],
        ['name' => 'Putih Tulang', 'slug' => 'putih-tulang', 'hex_code' => '#FBFAF7'],
        ['name' => 'Abu Misty', 'slug' => 'abu-misty', 'hex_code' => '#9CA3AF'],
        ['name' => 'Navy', 'slug' => 'navy', 'hex_code' => '#1B2A4A'],
        ['name' => 'Krem', 'slug' => 'krem', 'hex_code' => '#EFE7D6'],
        ['name' => 'Merah Marun', 'slug' => 'merah-marun', 'hex_code' => '#8B1A1A'],
        ['name' => 'Olive', 'slug' => 'olive', 'hex_code' => '#556B2F'],
        ['name' => 'Biru Denim', 'slug' => 'biru-denim', 'hex_code' => '#3B5B8C'],
    ];

    public function run(): void
    {
        foreach (self::COLORS as $color) {
            Color::updateOrCreate(
                ['slug' => $color['slug']],
                [
                    'name' => $color['name'],
                    'hex_code' => $color['hex_code'],
                    'is_active' => true,
                ]
            );
        }
    }
}
