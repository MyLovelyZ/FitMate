<?php

// Daftar label ukuran resmi FitMate: S, M, L, XL, atau 40, 41, 42 buat alas kaki.
// Toko ngk bikin label sendiri, tinggal milih dari sini biar 'XL' sama di semua toko.

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
        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_type_id')->constrained()->cascadeOnDelete();
            $table->string('size_type'); // top, bottom, footwear
            $table->string('name'); // label yang dilihat user: XL, 42
            $table->string('code')->nullable(); // kode internal kalau beda sama name
            $table->unsignedSmallInteger('sort_order')->default(0); // biar XS < S < M < L < XL urut bener
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['category_type_id', 'size_type', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sizes');
    }
};
