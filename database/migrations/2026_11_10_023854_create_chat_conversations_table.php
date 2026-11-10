<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Live chat pelanggan ↔ CS. Pelanggan punya paling banyak satu percakapan
     * `open` (dijaga `ChatConversation::forCustomer()`); setelah ditutup CS,
     * pesan berikutnya membuka percakapan baru.
     */
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // pelanggan
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // CS yang menangani
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_message_at']); // antrean CS
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
