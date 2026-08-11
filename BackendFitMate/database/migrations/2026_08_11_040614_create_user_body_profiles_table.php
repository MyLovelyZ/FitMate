<?php

// Done, maybe tapi harus dicheck lagi, karena belum semua migration dibuat

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_body_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('profile_name')->default('Profil Saya'); // Contoh: "Profil Saya", "Pasangan", "Adik"
            $table->enum('gender', ['male', 'female'])->nullable();

            // untuk bagian baju
            $table->numeric('lingkar_dada', 10, 2)->nullable();
            $table->numeric('panjang_badan', 10, 2)->nullable();
            $table->numeric('lebar_bahu', 10, 2)->nullable();

            // untuk bagian celana
            $table->numeric('lingkar_pinggang', 10, 2)->nullable();
            $table->numeric('lingkar_pinggul', 10, 2)->nullable();
            $table->numeric('panjang_kaki', 10, 2)->nullable();

            // untuk bagian sepatu
            $table->numeric('panjang_telapak_kaki', 10, 2)->nullable();
            $table->numeric('lebar_telapak_kaki', 10, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_body_profiles');
    }
};