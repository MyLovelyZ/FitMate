<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akun yang diblokir superadmin. `banned_at` terisi = tidak bisa masuk, sesi
     * yang masih aktif ikut dikeluarkan middleware `EnsureUserIsNotBanned`.
     * `ban_reason` ditampilkan ke pemilik akun saat mencoba masuk.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('banned_at')->nullable()->after('status');
            $table->foreignId('banned_by')->nullable()->after('banned_at')->constrained('users')->nullOnDelete();
            $table->text('ban_reason')->nullable()->after('banned_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('banned_by');
            $table->dropColumn(['banned_at', 'ban_reason']);
        });
    }
};
