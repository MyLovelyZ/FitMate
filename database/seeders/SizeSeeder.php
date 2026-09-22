<?php

namespace Database\Seeders;

use App\Models\CategoryType;
use App\Models\Size;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    /**
     * Label ukuran resmi FitMate. Butuh CategoryTypeSeeder jalan duluan.
     */
    public function run(): void
    {
        $sizesByType = [
            'pakaian-atasan' => ['top', ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
            'pakaian-bawahan' => ['bottom', ['28', '29', '30', '31', '32', '33', '34']],
            'alas-kaki' => ['footwear', ['38', '39', '40', '41', '42', '43', '44']],
        ];

        foreach ($sizesByType as $typeSlug => [$sizeType, $names]) {
            $type = CategoryType::where('slug', $typeSlug)->firstOrFail();

            foreach ($names as $index => $name) {
                Size::updateOrCreate(
                    [
                        'category_type_id' => $type->id,
                        'size_type' => $sizeType,
                        'name' => $name,
                    ],
                    [
                        'code' => $name,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
