<?php

// Cache hasil perhitungan ukuran. Hitungnya: ukuran badan user dibandingin ke rentang
// tiap entry di chart yang cocok (size_type dari kategori + gender), lalu diambil yang
// paling banyak nyangkut. Disimpan biar ngk usah ngitung ulang tiap buka produk,
// sekalian jadi data analitik.

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
        Schema::create('size_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_body_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('size_chart_id')->constrained()->onDelete('cascade');
            $table->foreignId('size_chart_entry_id')->nullable()->constrained()->onDelete('set null'); // hasilnya, contoh XL
            // null = rekomendasi umum per chart, terisi = khusus produk tertentu
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('fit_status', ['tight', 'fit', 'loose'])->default('fit');
            $table->decimal('fit_score', 5, 2)->default(0); // 0-100, seberapa yakin
            $table->unsignedTinyInteger('matched_measurements')->default(0); // dimensi yang masuk rentang
            $table->unsignedTinyInteger('total_measurements')->default(0); // dimensi yang dibandingin
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_body_profile_id', 'size_chart_id', 'product_id'], 'size_recommendation_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('size_recommendations');
    }
};
