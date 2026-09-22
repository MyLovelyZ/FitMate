<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            // Nullable: refund bisa sebagian, cuma untuk satu toko dalam satu order.
            $table->foreignId('store_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('escrow_hold_id')->nullable()->constrained()->nullOnDelete();
            $table->string('refund_number')->unique();

            $table->decimal('amount', 14, 2);
            // source = balik ke kartu/VA lewat gateway, wallet = masuk saldo pembeli.
            $table->enum('destination', ['source', 'wallet'])->default('source');
            $table->enum('status', [
                'requested', 'approved', 'processing', 'completed', 'rejected', 'failed',
            ])->default('requested');
            $table->text('reason');

            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('provider', 32)->nullable();
            $table->string('reference')->nullable()->unique();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
