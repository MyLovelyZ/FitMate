<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_order_id')->constrained()->cascadeOnDelete();

            // FK-nya nullable dan set null: produk boleh hilang, notanya tetap utuh
            // karena semua yang penting sudah disalin ke kolom di bawah.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            $table->string('product_name');
            $table->string('variant_sku')->nullable();
            $table->string('size_label', 20)->nullable();
            $table->string('color_name')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 14, 2);

            // Ukuran yang disarankan FitMate saat checkout, buat mengukur seberapa
            // sering pembeli menuruti rekomendasi.
            $table->string('recommended_size_label', 20)->nullable();

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
