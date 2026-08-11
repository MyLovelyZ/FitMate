<?php

// Kamus dimensi ukur. Ini sumber kebenaran tunggalnya: dipakai bareng-bareng oleh
// ukuran badan user, rentang standar FitMate, dan ukuran asli produk.
// Admin bisa nambah dimensi baru lewat data, ngk perlu migration lagi.

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
        Schema::create('body_measurements', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // lingkar_dada, lingkar_pinggang, panjang_telapak_kaki
            $table->string('label'); // Lingkar Dada
            $table->string('description')->nullable(); // panduan cara ngukurnya buat user
            $table->string('unit', 10)->default('cm'); // cm, kg
            // 'general' buat tinggi & berat badan yang kepakai di semua perhitungan
            $table->enum('applies_to', ['general', 'top', 'bottom', 'footwear']);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('body_measurements');
    }
};
