<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = ['Nusantara Wear', 'Kalapa', 'Rimba Goods', 'Selaras', 'Langkah Kaki', 'Tanpa Merek'];

        foreach ($brands as $name) {
            Brand::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );
        }
    }
}
