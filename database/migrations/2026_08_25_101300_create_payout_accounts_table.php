<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['bank', 'ewallet']);
            $table->string('bank_code', 32)->nullable(); // bca, bni, gopay, ...
            $table->string('account_name');
            $table->string('account_number', 64);
            $table->boolean('is_default')->default(false);
            // Diisi setelah nama pemilik rekening dicek ke provider. Penarikan hanya
            // boleh ke rekening yang sudah terverifikasi.
            $table->timestamp('verified_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // NOTE: MySQL ngk menahan baris ber-NULL, jadi kombinasi dengan bank_code
            // null tetap bisa dobel. Dijaga di kode.
            $table->unique(['user_id', 'type', 'bank_code', 'account_number'], 'payout_account_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_accounts');
    }
};
