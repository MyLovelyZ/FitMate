<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // nullable buat produk tanpa ukuran/warna (aksesoris).
            // restrict: ukuran & warna yang masih dipakai ngk boleh dihapus, karena
            // kalau jadi null varian ini kehilangan identitasnya dan nabrak varian lain.
            $table->foreignId('size_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('color_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('sku')->nullable()->unique();
            $table->decimal('price', 12, 2)->nullable(); // null = ikut products.base_price
            $table->unsignedInteger('stock')->default(0);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // NOTE: MySQL ngk nahan baris ber-NULL, jadi kombinasi (null, null) tetap
            // bisa dobel. Produk tanpa ukuran/warna dijaga di kode, bukan di sini.
            $table->unique(['product_id', 'size_id', 'color_id'], 'product_variant_combination_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
