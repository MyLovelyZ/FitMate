<?php

// Kombinasi ukuran + warna yang benar-benar dijual dan punya stok.
// Ukurannya nunjuk ke entry standar FitMate, bukan teks bebas, biar seller ngk bisa
// bikin definisi 'XL' sendiri.

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
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('size_chart_entry_id')->nullable()->constrained()->onDelete('set null');
            $table->string('sku')->unique();
            $table->string('color_name')->nullable();
            $table->string('color_hex', 7)->nullable(); // #1A2B3C
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable(); // harga sebelum diskon, buat coret-coretan
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('weight_gram')->nullable(); // kalau null pakai berat produknya
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'size_chart_entry_id', 'color_name'], 'product_variant_combination_unique');
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
