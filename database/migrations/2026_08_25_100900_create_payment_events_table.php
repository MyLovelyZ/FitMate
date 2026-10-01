<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            // Nullable: webhook bisa datang untuk referensi yang belum kita kenal,
            // dan tetap harus tercatat supaya bisa ditelusuri.
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32);
            $table->string('event_id'); // id notifikasi dari provider
            $table->string('event_type')->nullable();
            $table->string('signature')->nullable(); // buat verifikasi ulang saat audit
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Inti pengaman uang: gateway mengirim webhook berkali-kali. Tanpa unique
            // ini, satu pembayaran bisa mengisi wallet seller berulang kali.
            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
