<?php

// Selain rating biasa, di sini ada fit_feedback. Ini yang bikin standar FitMate
// bisa dikoreksi: kalau banyak yang bilang 'L' di kategori tertentu kekecilan,
// admin punya bukti buat nyetel ulang rentangnya.

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
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // satu item order cuma boleh direview sekali. null kalau review tanpa pembelian
            $table->foreignId('order_item_id')->nullable()->unique()->constrained()->onDelete('set null');
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->enum('fit_feedback', ['too_small', 'slightly_small', 'true_to_size', 'slightly_large', 'too_large'])->nullable();
            $table->string('size_purchased', 20)->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
