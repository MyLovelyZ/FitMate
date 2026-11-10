<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ulasan pembeli per barang yang sudah diterima. Satu `order_item` satu
     * ulasan, jadi hanya pembeli yang benar-benar membeli yang bisa menilai.
     *
     * `fit_feedback` = umpan balik ukuran (kekecilan / pas / kebesaran): bahan
     * untuk menyetel ulang tabel ukuran FitMate nanti.
     */
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1–5, dijaga validasi
            $table->enum('fit_feedback', ['too_small', 'fit', 'too_large'])->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']); // daftar ulasan di halaman produk
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
