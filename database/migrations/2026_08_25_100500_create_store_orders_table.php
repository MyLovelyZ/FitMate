<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->string('store_order_number')->unique();

            $table->decimal('subtotal', 14, 2);
            $table->decimal('shipping_cost', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total', 14, 2);

            // Komisi dikunci saat checkout. Kalau tarif platform berubah bulan depan,
            // order lama tetap dihitung pakai tarif yang berlaku waktu itu.
            $table->decimal('platform_fee_rate', 5, 4)->default(0);
            $table->decimal('platform_fee_amount', 14, 2)->default(0);
            $table->decimal('seller_earning', 14, 2); // total - platform_fee_amount

            // Status jalan sendiri-sendiri per toko karena tiap toko mengirim terpisah.
            $table->enum('status', [
                'pending', 'processing', 'shipped', 'delivered',
                'completed', 'cancelled', 'refunded', 'disputed',
            ])->default('pending');

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('buyer_confirmed_at')->nullable(); // pemicu rilis escrow
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'store_id']);
            $table->index(['store_id', 'status']); // dashboard seller
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_orders');
    }
};
