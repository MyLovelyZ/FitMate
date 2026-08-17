<?php

// Ukuran badan user. Angkanya disimpan langsung sebagai kolom di sini,
// lalu dicocokkan ke rentang di size_guides buat nentuin ukuran yang pas.

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
        Schema::create('user_body_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('profile_name')->default('Profil Saya'); // Contoh: "Profil Saya", "Pasangan", "Adik"
            $table->enum('gender', ['male', 'female'])->nullable(); // dipakai buat milih ukuran yang sesuai
            $table->boolean('is_default')->default(false); // profil utama yang dipakai kalau user ngk milih

            // Semua nullable: user boleh isi bertahap, rekomendasi jalan pakai yang udh ada.
            $table->decimal('height', 5, 2)->nullable(); // cm
            $table->decimal('weight', 5, 2)->nullable(); // kg
            $table->decimal('chest', 5, 2)->nullable(); // lingkar dada, cm
            $table->decimal('waist', 5, 2)->nullable(); // lingkar pinggang, cm
            $table->decimal('hip', 5, 2)->nullable(); // lingkar pinggul, cm
            $table->decimal('foot_length', 5, 2)->nullable(); // panjang telapak kaki, cm

            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_body_profiles');
    }
};
