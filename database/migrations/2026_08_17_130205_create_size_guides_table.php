<?php

// Inti standarisasinya: jawaban dari "XL itu berapa?".
// Satu baris = satu dimensi buat satu ukuran.
// Contoh: size XL + lingkar_dada => min 104.00, max 108.00.
// Nambah dimensi baru cukup lewat data, ngk perlu migration lagi.

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
        Schema::create('size_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_id')->constrained()->cascadeOnDelete();
            $table->string('measurement_key'); // lingkar_dada, lingkar_pinggang, panjang_telapak_kaki
            $table->string('label'); // Lingkar Dada
            $table->string('unit', 10)->default('cm');
            $table->decimal('min_value', 6, 2);
            $table->decimal('max_value', 6, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // satu dimensi cuma boleh sekali per ukuran
            $table->unique(['size_id', 'measurement_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('size_guides');
    }
};
