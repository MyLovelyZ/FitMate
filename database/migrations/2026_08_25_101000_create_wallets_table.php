<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            // Semua user punya wallet: seller buat menerima hasil penjualan,
            // pembeli buat menampung refund.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('currency', 3)->default('IDR');

            // Kedua kolom ini CACHE, bukan sumber kebenaran.
            // balance_available = SUM(wallet_transactions) untuk wallet ini.
            // balance_pending   = SUM(escrow_holds.net_amount) yang masih 'held'.
            // Cuma boleh diubah di dalam transaksi database dengan lockForUpdate().
            $table->decimal('balance_available', 16, 2)->default(0);
            $table->decimal('balance_pending', 16, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
