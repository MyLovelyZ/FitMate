<?php

// Angka ukuran badan milik user, satu baris per dimensi.
// User input sekali di sini, hasilnya kepakai di semua toko.

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
        Schema::create('user_body_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_body_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('body_measurement_id')->constrained()->onDelete('cascade');
            $table->decimal('value', 6, 2);
            $table->timestamps();

            // satu dimensi cuma boleh sekali per profil
            $table->unique(['user_body_profile_id', 'body_measurement_id'], 'user_body_measurement_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_body_measurements');
    }
};
