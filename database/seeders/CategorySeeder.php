<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Butuh CategoryTypeSeeder jalan duluan.
     */
    public function run(): void
    {
        $categoriesByType = [
            'pakaian-atasan' => ['Kaos', 'Kemeja', 'Jaket', 'Hoodie', 'Sweater'],
            'pakaian-bawahan' => ['Celana Jeans', 'Celana Chino', 'Celana Pendek', 'Rok'],
            'alas-kaki' => ['Sepatu Sneakers', 'Sepatu Formal', 'Sandal'],
            'aksesoris' => ['Tas', 'Topi', 'Ikat Pinggang'],
        ];

        foreach ($categoriesByType as $typeSlug => $names) {
            $type = CategoryType::where('slug', $typeSlug)->firstOrFail();

            foreach ($names as $name) {
                Category::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    [
                        'category_type_id' => $type->id,
                        'name' => $name,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
