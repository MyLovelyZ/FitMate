<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // restrict: user yang punya riwayat order ngk boleh dihapus begitu saja.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('order_number')->unique();

            // Alamat disalin, bukan FK ke user_addresses: kalau user mengedit atau
            // menghapus alamatnya besok, nota lama harus tetap menunjukkan yang dulu.
            $table->string('recipient_name');
            $table->string('recipient_phone', 32);
            $table->text('shipping_street');
            $table->string('shipping_city');
            $table->string('shipping_province');
            $table->string('shipping_postal_code', 10);
            $table->string('shipping_country', 64)->default('Indonesia');

            $table->decimal('subtotal', 14, 2);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2);

            $table->enum('status', [
                'pending_payment', 'paid', 'processing', 'shipped',
                'completed', 'cancelled', 'expired', 'refunded',
            ])->default('pending_payment');

            $table->text('note')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // batas waktu bayar QR/VA
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']); // scheduler pembatal order kedaluwarsa
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
