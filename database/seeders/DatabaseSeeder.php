<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Urutan penting: kategori dan ukuran dipakai seeder di bawahnya.
        $this->call([
            CategoryTypeSeeder::class,
            CategorySeeder::class,
            SizeSeeder::class,
            SizeGuideSeeder::class,
            UserSeeder::class,
        ]);
    }
}
