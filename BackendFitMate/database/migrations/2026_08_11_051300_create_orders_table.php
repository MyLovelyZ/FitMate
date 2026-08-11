<?php

// Order level pembeli: satu kali checkout, satu kali bayar, walaupun barangnya
// dari beberapa toko sekaligus. Pecahan per tokonya ada di store_orders.

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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->string('order_number')->unique();
            $table->foreignId('coupon_id')->nullable()->constrained()->onDelete('set null');

            // alamat disalin, bukan direferensi, biar riwayat order ngk berubah
            // kalau user ngedit atau ngehapus alamatnya nanti
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->text('shipping_street');
            $table->string('shipping_city');
            $table->string('shipping_state');
            $table->string('shipping_postal_code');
            $table->string('shipping_country');

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->enum('status', ['pending_payment', 'paid', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'])->default('pending_payment');
            $table->text('note')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
