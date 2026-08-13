<?php

// Pembayaran nempel di order level pembeli, bukan per toko: user bayar sekali,
// nanti backend yang bagi-bagi ke seller.

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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->enum('method', ['bank_transfer', 'virtual_account', 'ewallet', 'credit_card', 'cod']);
            $table->string('provider')->nullable(); // midtrans, xendit, dll
            $table->string('reference')->nullable()->index(); // nomor transaksi dari provider
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('payload')->nullable(); // respon mentah dari provider buat jaga-jaga
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
