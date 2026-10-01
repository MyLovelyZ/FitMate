<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            // Inilah alasan escrow ada. Sengketa yang terbuka menahan auto-release
            // sampai admin memutuskan, jadi uangnya ngk telanjur cair ke seller.
            $table->foreignId('store_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();

            $table->enum('reason', [
                'not_received', 'not_as_described', 'damaged', 'wrong_item', 'other',
            ]);
            $table->text('description');
            $table->enum('status', [
                'open', 'under_review', 'resolved_buyer', 'resolved_seller', 'cancelled',
            ])->default('open');

            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['store_order_id', 'status']);
            $table->index(['status', 'created_at']); // antrean kerja admin
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
