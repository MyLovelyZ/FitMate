<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Produk yang diturunkan superadmin. Terpisah dari `is_active` (milik seller)
     * supaya seller tidak bisa menayangkannya lagi sendiri.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('banned_at')->nullable()->after('is_active');
            $table->foreignId('banned_by')->nullable()->after('banned_at')->constrained('users')->nullOnDelete();
            $table->text('ban_reason')->nullable()->after('banned_by');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('banned_by');
            $table->dropColumn(['banned_at', 'ban_reason']);
        });
    }
};
