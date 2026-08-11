<?php

// Semua yang penting disalin ke sini pas checkout. Kalau seller ngubah harga atau
// ngehapus produknya besok, nota lama harus tetap nunjukin angka yang dulu.

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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('set null');

            $table->string('product_name');
            $table->string('size_label', 20)->nullable();
            $table->string('color_name')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('subtotal', 14, 2);

            // ukuran yang disaranin FitMate waktu itu. dibandingin sama size_label
            // buat ngukur seberapa sering user nurut sama rekomendasinya
            $table->string('recommended_size_label', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
