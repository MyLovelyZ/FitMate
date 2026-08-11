<?php

// Ukuran jadi barangnya (bukan ukuran badan) yang diisi seller per varian.
// Gunanya buat verifikasi: kalau angka seller nyimpang jauh dari rentang standar,
// admin bisa nolak produknya. Juga dipakai nampilin detail ukuran di halaman produk.

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
        Schema::create('product_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->onDelete('cascade');
            $table->foreignId('body_measurement_id')->constrained()->onDelete('cascade');
            $table->decimal('value', 6, 2);
            $table->timestamps();

            $table->unique(['product_variant_id', 'body_measurement_id'], 'product_variant_measurement_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_measurements');
    }
};
