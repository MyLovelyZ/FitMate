<?php

// Kategori bertingkat: Pakaian > Kaos, Alas Kaki > Sandal, dst.
// size_type yang nentuin produk di kategori ini ikut standar ukuran yang mana.

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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['clothing', 'footwear', 'accessories']);
            // 'none' buat topi, kacamata, jam tangan, kalung: ngk ikut perhitungan ukuran
            $table->enum('size_type', ['top', 'bottom', 'footwear', 'none'])->default('none');
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
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
        Schema::dropIfExists('categories');
    }
};
