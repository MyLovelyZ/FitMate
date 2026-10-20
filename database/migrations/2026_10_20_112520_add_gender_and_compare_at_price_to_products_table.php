<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dipakai menu katalog "Men", "Women", dan "On Sale".
     *
     * - `gender`: unisex ikut tampil di Men maupun Women.
     * - `compare_at_price`: harga coret. Produk dianggap diskon kalau nilainya
     *   lebih besar dari `base_price`; null = tidak sedang diskon.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('gender', ['men', 'women', 'unisex'])->default('unisex')->after('description');
            $table->decimal('compare_at_price', 12, 2)->nullable()->after('base_price');

            $table->index(['is_active', 'gender']); // filter Men/Women di katalog
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'gender']);
            $table->dropColumn(['gender', 'compare_at_price']);
        });
    }
};
