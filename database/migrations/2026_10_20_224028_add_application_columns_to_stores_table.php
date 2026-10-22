<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengajuan toko (KEP-5: seller mendaftar sendiri lalu diverifikasi admin).
     *
     * - Identitas pemilik dipakai admin untuk verifikasi. `identity_number` (NIK)
     *   disimpan terenkripsi lewat cast model, jadi kolomnya `text`, bukan
     *   `string(16)`. Foto KTP ada di disk privat `local`, bukan `public`.
     * - `review_note` = alasan penolakan/penangguhan yang ditampilkan ke pemilik.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('identity_name')->nullable()->after('postal_code');
            $table->text('identity_number')->nullable()->after('identity_name');
            $table->string('identity_card_path')->nullable()->after('identity_number');

            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');

            $table->index(['status', 'submitted_at']); // antrean review admin
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['status', 'submitted_at']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'identity_name',
                'identity_number',
                'identity_card_path',
                'submitted_at',
                'reviewed_at',
                'review_note',
            ]);
        });
    }
};
