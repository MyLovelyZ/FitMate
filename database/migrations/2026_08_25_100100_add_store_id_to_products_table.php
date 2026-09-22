<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Produk lama (hasil seeder sebelum multi-seller) belum punya toko, jadi kolomnya
     * dibuat nullable dulu, diisi, baru dikunci NOT NULL.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $this->assignLegacyProductsToAStore();

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable(false)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['store_id', 'is_active']); // katalog per toko
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'is_active']);
            $table->dropConstrainedForeignId('store_id');
        });
    }

    private function assignLegacyProductsToAStore(): void
    {
        if (! DB::table('products')->whereNull('store_id')->exists()) {
            return;
        }

        $ownerId = DB::table('users')->where('role', 'seller')->value('id')
            ?? DB::table('users')->value('id');

        if ($ownerId === null) {
            throw new RuntimeException(
                'Ada produk tanpa toko tapi belum ada user yang bisa dijadikan pemiliknya. '
                .'Jalankan migrasi ini setelah UserSeeder, atau kosongkan tabel products dulu.'
            );
        }

        $storeId = DB::table('stores')->where('slug', 'fitmate-official')->value('id')
            ?? DB::table('stores')->insertGetId([
                'user_id' => $ownerId,
                'name' => 'FitMate Official',
                'slug' => 'fitmate-official',
                'status' => 'active',
                'verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('products')->whereNull('store_id')->update(['store_id' => $storeId]);
    }
};
