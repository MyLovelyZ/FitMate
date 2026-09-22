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
        // Urutan penting. UserSeeder naik ke atas karena StoreSeeder butuh akun
        // seller, dan ProductSeeder butuh toko sebagai pemilik produknya.
        $this->call([
            CategoryTypeSeeder::class,
            CategorySeeder::class,
            SizeSeeder::class,
            SizeGuideSeeder::class,
            ColorSeeder::class,
            UserSeeder::class,
            StoreSeeder::class,
            ProductSeeder::class,
            WalletSeeder::class,
            MarketplaceOrderSeeder::class,
        ]);
    }
}
