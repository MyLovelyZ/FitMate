<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Menempel di order, bukan di store_order: pembeli bayar sekali untuk
            // seluruh keranjang, platform yang membagi ke tiap toko setelahnya.
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->enum('method', ['qris', 'virtual_account', 'bank_transfer', 'ewallet', 'credit_card']);
            $table->string('provider', 32)->nullable(); // midtrans, xendit, ...

            // Unique, bukan sekadar index: ini yang menahan satu transaksi gateway
            // dicatat dua kali kalau webhook-nya dikirim ulang.
            $table->string('reference')->nullable()->unique();

            // Kode QR (QRIS) dan nomor VA disimpan supaya halaman bayar bisa
            // menampilkan ulang instruksi yang sama tanpa memanggil gateway lagi.
            $table->text('qr_string')->nullable();
            $table->string('qr_image_url')->nullable();
            $table->string('va_bank', 32)->nullable();
            $table->string('va_number', 64)->nullable();

            $table->decimal('amount', 14, 2);
            $table->decimal('provider_fee', 14, 2)->default(0); // biaya gateway
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('payload')->nullable(); // respon mentah provider, wajib $hidden di model
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
