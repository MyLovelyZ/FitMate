<?php

// Standar ukuran resmi FitMate. Cuma admin yang boleh bikin atau ngubah.
// Toko ngk punya chart sendiri, mereka tinggal milih label yang udh ada di sini,
// jadi 'XL' nilainya sama persis di seluruh toko.

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
        Schema::create('size_charts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Contoh: "Atasan Pria", "Alas Kaki Unisex"
            $table->string('slug')->unique();
            $table->enum('size_type', ['top', 'bottom', 'footwear']);
            $table->enum('gender', ['male', 'female', 'unisex'])->default('unisex');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // kunci keputusannya di level database: satu standar aja per kombinasi
            $table->unique(['size_type', 'gender']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('size_charts');
    }
};
