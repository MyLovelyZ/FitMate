<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow_holds', function (Blueprint $table) {
            $table->id();
            // Satu tahanan dana per pesanan-toko: uang dilepas per toko, karena tiap
            // toko mengirim sendiri dan bisa diterima di waktu yang berbeda.
            $table->foreignId('store_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete(); // uangnya dari mana
            $table->foreignId('seller_wallet_id')->constrained('wallets')->restrictOnDelete(); // cair ke mana

            $table->decimal('gross_amount', 14, 2);        // yang dibayar pembeli untuk toko ini
            $table->decimal('platform_fee_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2);          // gross - fee, hak seller
            $table->decimal('refunded_amount', 14, 2)->default(0);

            $table->enum('status', [
                'held', 'released', 'refunded', 'partially_refunded', 'cancelled',
            ])->default('held');

            $table->enum('release_reason', [
                'buyer_confirmed',  // pembeli menekan "barang diterima"
                'auto_released',    // pembeli diam sampai batas waktu
                'admin_released',
                'dispute_resolved',
            ])->nullable();

            $table->timestamp('held_at');
            // Diisi saat kurir menandai terkirim. Kalau pembeli diam sampai lewat
            // tanggal ini, scheduler mencairkan sendiri.
            $table->timestamp('auto_release_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            // Dipakai scheduler auto-release; tanpa index ini dia scan seluruh tabel.
            $table->index(['status', 'auto_release_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow_holds');
    }
};
