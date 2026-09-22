<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('payout_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payout_number')->unique();

            // Rekening tujuan disalin: user boleh menghapus rekeningnya besok,
            // bukti transfer harus tetap menunjukkan ke mana uangnya dikirim.
            $table->string('account_bank_code', 32)->nullable();
            $table->string('account_name');
            $table->string('account_number', 64);

            $table->decimal('amount', 14, 2);
            $table->decimal('fee_amount', 14, 2)->default(0); // biaya transfer
            $table->decimal('net_amount', 14, 2);

            $table->enum('status', ['requested', 'processing', 'completed', 'failed', 'cancelled'])
                ->default('requested');

            $table->string('provider', 32)->nullable();
            $table->string('reference')->nullable()->unique(); // id disbursement provider
            $table->text('failure_reason')->nullable();

            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
