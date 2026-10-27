<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil toko (nama, slug, deskripsi, kontak, logo) baru diisi pemilik
     * setelah pengajuannya disetujui admin. Pengajuan hanya berisi alamat asal
     * pengiriman, identitas pemilik, dan rekening, jadi `name` dan `slug` kosong
     * sampai profilnya dilengkapi. Index unique `slug` tetap: banyak NULL boleh.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('slug')->nullable()->change();
        });
    }

    /**
     * Gagal kalau masih ada toko tanpa profil; lengkapi atau hapus dulu barisnya.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('slug')->nullable(false)->change();
        });
    }
};
