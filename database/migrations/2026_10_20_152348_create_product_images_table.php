<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Galeri foto produk. Foto bertanda `is_primary` yang tampil di kartu katalog.
     */
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // relatif ke public/, langsung dipakai asset()
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            // hanya satu per produk; dijaga di model ProductImage, bukan di skema
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'is_primary']); // gambar utama di katalog
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
