<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            // restrict: buku besar uang ngk boleh ikut terhapus.
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();

            $table->enum('type', [
                'escrow_release',   // escrow cair ke seller
                'platform_fee',     // komisi platform dipotong
                'payout',           // saldo ditarik ke rekening
                'payout_reversal',  // penarikan gagal, saldo dikembalikan
                'refund',           // refund masuk ke saldo pembeli
                'topup',
                'adjustment',       // koreksi manual admin, wajib ada alasannya
            ]);
            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 16, 2); // selalu positif, arahnya dari `direction`
            $table->decimal('balance_after', 16, 2); // jejak audit per baris

            // Asalnya dari mana: StoreOrder, EscrowHold, Payout, atau Refund.
            $table->nullableMorphs('reference');

            // Wajib diisi dan unique. Ini yang bikin operasi uang aman diulang:
            // percobaan kedua dengan kunci yang sama akan ditolak database.
            $table->string('idempotency_key')->unique();

            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
