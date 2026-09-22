<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            // Per toko, karena tiap toko punya resi sendiri.
            $table->foreignId('store_order_id')->constrained()->cascadeOnDelete();
            $table->string('courier', 32);
            $table->string('service', 32)->nullable();
            $table->string('tracking_number', 64)->nullable()->index();
            $table->decimal('cost', 12, 2)->default(0);
            $table->unsignedInteger('weight_gram')->default(0);
            $table->enum('status', [
                'pending', 'picked_up', 'in_transit', 'delivered', 'failed', 'returned',
            ])->default('pending');
            // 'delivered' di sini yang menyalakan hitungan mundur auto-release escrow.
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['store_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
