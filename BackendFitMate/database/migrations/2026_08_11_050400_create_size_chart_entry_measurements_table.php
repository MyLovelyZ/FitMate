<?php

// Ini inti standarisasinya: jawaban dari "XL itu berapa?".
// Contoh baris: entry XL + dimensi lingkar_dada => min 104.00, max 108.00.

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
        Schema::create('size_chart_entry_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_chart_entry_id')->constrained()->onDelete('cascade');
            $table->foreignId('body_measurement_id')->constrained()->onDelete('cascade');
            $table->decimal('min_value', 6, 2);
            $table->decimal('max_value', 6, 2);
            $table->timestamps();

            $table->unique(['size_chart_entry_id', 'body_measurement_id'], 'size_entry_measurement_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('size_chart_entry_measurements');
    }
};
