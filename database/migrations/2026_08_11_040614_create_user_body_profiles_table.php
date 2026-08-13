<?php

// Angka ukurannya pindah ke tabel user_body_measurements (pakai kamus body_measurements),
// jadi di sini tinggal identitas profilnya saja.

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
            $table->enum('gender', ['male', 'female'])->nullable(); // dipakai buat milih size_charts yang sesuai
            $table->boolean('is_default')->default(false); // profil utama yang dipakai kalau user ngk milih
            $table->timestamps();
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
