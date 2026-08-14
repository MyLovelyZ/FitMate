<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder data acuan dijalankan selalu; seeder data contoh hanya di luar
     * produksi. Urutannya penting — SizeChartSeeder butuh kamus dimensi, dan
     * DemoContentSeeder butuh kategori beserta chart-nya.
     */
    public function run(): void
    {
        $this->call([
            BodyMeasurementSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            SizeChartSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DemoContentSeeder::class);
        }
    }
}
