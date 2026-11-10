<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Produk yang disimpan pembeli untuk dibeli nanti. Pivot murni
     * (`User::wishlist()`), jadi tidak punya model sendiri.
     */
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']); // satu produk sekali per pembeli
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
