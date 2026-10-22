<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voucher yang dipakai saat checkout. `voucher_code` disalin supaya nota
     * tetap menunjukkan kodenya walaupun vouchernya dihapus admin; `voucher_id`
     * dipakai menghitung kuota pemakaian.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('shipping_total')->constrained()->nullOnDelete();
            $table->string('voucher_code', 32)->nullable()->after('voucher_id');

            $table->index(['voucher_id', 'user_id', 'status']); // cek kuota per pembeli
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['voucher_id', 'user_id', 'status']);
            $table->dropConstrainedForeignId('voucher_id');
            $table->dropColumn('voucher_code');
        });
    }
};
