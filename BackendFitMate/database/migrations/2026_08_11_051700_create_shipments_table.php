<?php

// Pengiriman per toko, karena tiap toko punya resi sendiri.

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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_order_id')->constrained()->onDelete('cascade');
            $table->string('courier'); // jne, jnt, sicepat
            $table->string('service')->nullable(); // reg, yes, cargo
            $table->string('tracking_number')->nullable()->index();
            $table->decimal('cost', 12, 2)->default(0);
            $table->unsignedInteger('weight_gram')->default(0);
            $table->enum('status', ['pending', 'picked_up', 'in_transit', 'delivered', 'failed', 'returned'])->default('pending');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
