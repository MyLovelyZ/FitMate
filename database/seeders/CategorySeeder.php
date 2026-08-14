<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Enums\SizeType;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Kategori bertingkat beserta `size_type`-nya.
 *
 * `size_type` adalah penghubung ke mesin ukuran: kategori bernilai `none`
 * (aksesoris) otomatis dilewati saat rekomendasi ukuran dihitung.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            [
                'name' => 'Pakaian',
                'type' => CategoryType::Clothing,
                'size_type' => SizeType::Top,
                'children' => [
                    ['name' => 'Kaos', 'size_type' => SizeType::Top],
                    ['name' => 'Kemeja', 'size_type' => SizeType::Top],
                    ['name' => 'Jaket', 'size_type' => SizeType::Top],
                    ['name' => 'Celana Panjang', 'size_type' => SizeType::Bottom],
                    ['name' => 'Celana Pendek', 'size_type' => SizeType::Bottom],
                    ['name' => 'Rok', 'size_type' => SizeType::Bottom],
                ],
            ],
            [
                'name' => 'Alas Kaki',
                'type' => CategoryType::Footwear,
                'size_type' => SizeType::Footwear,
                'children' => [
                    ['name' => 'Sepatu', 'size_type' => SizeType::Footwear],
                    ['name' => 'Sandal', 'size_type' => SizeType::Footwear],
                ],
            ],
            [
                'name' => 'Aksesoris',
                'type' => CategoryType::Accessories,
                'size_type' => SizeType::None,
                'children' => [
                    ['name' => 'Topi', 'size_type' => SizeType::None],
                    ['name' => 'Kacamata', 'size_type' => SizeType::None],
                    ['name' => 'Jam Tangan', 'size_type' => SizeType::None],
                    ['name' => 'Kalung', 'size_type' => SizeType::None],
                ],
            ],
        ];

        foreach ($tree as $rootOrder => $root) {
            $parent = $this->upsertCategory($root['name'], $root['type'], $root['size_type'], $rootOrder + 1);

            foreach ($root['children'] as $childOrder => $child) {
                $this->upsertCategory($child['name'], $root['type'], $child['size_type'], $childOrder + 1, $parent->id);
            }
        }
    }

    private function upsertCategory(string $name, CategoryType $type, SizeType $sizeType, int $sortOrder, ?int $parentId = null): Category
    {
        return Category::updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'parent_id' => $parentId,
                'name' => $name,
                'type' => $type,
                'size_type' => $sizeType,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ],
        );
    }
}
