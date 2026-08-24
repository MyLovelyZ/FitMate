<?php

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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // restrict: kategori yang masih dipakai produk ngk boleh dihapus
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // harga tampilan di katalog; harga yang ditagih ada di varian
            $table->decimal('base_price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->softDeletes(); // produk kehapus ngk boleh ngerusak riwayat order
            $table->timestamps();

            $table->index(['is_active', 'category_id']); // query katalog
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
