<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat pelacakan paket (timeline yang dilihat pembeli). Satu baris per
     * perubahan status pengiriman. Sementara diisi seller lewat
     * `StoreOrder::updateShipmentStatus()`; nanti bisa diisi webhook kurir (KEP-4).
     */
    public function up(): void
    {
        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            // Sama dengan enum shipments.status
            $table->enum('status', ['pending', 'picked_up', 'in_transit', 'delivered', 'failed', 'returned']);
            $table->string('description');
            $table->string('location', 120)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shipment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
    }
};
