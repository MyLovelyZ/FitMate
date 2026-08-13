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
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            $table->foreignId('brand_id')->nullable()->constrained()->onDelete('set null');
            // chart yang diikuti produk ini. null buat aksesoris yang ngk ada ukurannya
            $table->foreignId('size_chart_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('target_gender', ['male', 'female', 'unisex'])->default('unisex');
            $table->decimal('base_price', 12, 2); // harga tampilan, harga aslinya per varian
            $table->unsignedInteger('weight_gram')->default(0);
            $table->enum('status', ['draft', 'pending_review', 'active', 'inactive', 'rejected'])->default('draft');
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('sold_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); // produk yang udh pernah dibeli jangan sampe ilang dari riwayat order

            $table->index(['status', 'category_id']);
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
