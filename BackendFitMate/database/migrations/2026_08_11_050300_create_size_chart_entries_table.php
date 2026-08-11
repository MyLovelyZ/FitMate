<?php

// Baris label di dalam satu chart: S, M, L, XL, XXL, atau 40, 41, 42 buat alas kaki.

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
        Schema::create('size_chart_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_chart_id')->constrained()->onDelete('cascade');
            $table->string('label', 20);
            $table->unsignedSmallInteger('sort_order')->default(0); // biar XS < S < M < L < XL urut bener
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['size_chart_id', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('size_chart_entries');
    }
};
