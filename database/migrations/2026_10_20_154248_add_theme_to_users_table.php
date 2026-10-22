<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preferensi tema dari tab "Tampilan" di profil, dirender ke `<html data-theme>`.
     * `system` mengikuti pengaturan terang/gelap perangkat.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('theme', ['light', 'dark', 'system'])->default('system')->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
