<?php

// Masih on progress!! belum selesai, masih ada beberapa field yang perlu ditambahkan

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
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('label')->default('Home'); // Contoh: Home, Work, Other pokoknya ini bagian alamat atau tag
            $table->string('recipient_name'); // ini siapa penerimanya
            $table->string('recipient_phone'); // ini nomor telepon penerimanya
            $table->text('street'); // ini jalan atau alamat lengkapnya
            $table->string('city'); // kota
            $table->string('state'); // negara bagian atau provinsi
            $table->string('postal_code'); // kode pos
            $table->string('country'); // negara indonesia udh pasti, cuma kita go international ngk yah @backend?
            $table->boolean('is_default')->default(false); // ini untuk menandai alamat default pengguna
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
