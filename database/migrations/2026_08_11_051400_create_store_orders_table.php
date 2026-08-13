<?php

// Pecahan order per toko. Ini yang dilihat dan diproses seller, dan yang statusnya
// jalan sendiri-sendiri, karena tiap toko ngirimnya beda-beda.

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
        Schema::create('store_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->constrained()->onDelete('restrict');
            $table->string('store_order_number')->unique();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('shipping_cost', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded'])->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_orders');
    }
};
