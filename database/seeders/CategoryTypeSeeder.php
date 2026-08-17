<?php

namespace Database\Seeders;

use App\Models\CategoryType;
use Illuminate\Database\Seeder;

class CategoryTypeSeeder extends Seeder
{
    /**
     * Jenis kategori nentuin produk di bawahnya pakai jenis ukuran yang mana.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Pakaian Atasan',
                'slug' => 'pakaian-atasan',
                'has_sizes' => true,
                'default_size_type' => 'top',
                'description' => 'Kaos, kemeja, jaket, dan sejenisnya. Diukur dari lingkar dada.',
            ],
            [
                'name' => 'Pakaian Bawahan',
                'slug' => 'pakaian-bawahan',
                'has_sizes' => true,
                'default_size_type' => 'bottom',
                'description' => 'Celana dan rok. Diukur dari lingkar pinggang dan pinggul.',
            ],
            [
                'name' => 'Alas Kaki',
                'slug' => 'alas-kaki',
                'has_sizes' => true,
                'default_size_type' => 'footwear',
                'description' => 'Sepatu dan sandal. Diukur dari panjang telapak kaki.',
            ],
            [
                'name' => 'Aksesoris',
                'slug' => 'aksesoris',
                'has_sizes' => false,
                'default_size_type' => 'none',
                'description' => 'Tas, topi, ikat pinggang. Ngk butuh ukuran badan.',
            ],
        ];

        foreach ($types as $type) {
            CategoryType::updateOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
