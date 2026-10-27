<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kode promo yang ditukar pembeli di checkout.
     *
     * - `percentage`: `value` = persen (1–100) dari subtotal barang, dibatasi
     *   `max_discount` kalau diisi. `fixed`: `value` = potongan rupiah.
     * - Diskon hanya memotong subtotal barang, bukan ongkir.
     * - Jumlah pemakaian dihitung dari `orders.voucher_id`, bukan kolom counter:
     *   order yang batal/kedaluwarsa sebelum dibayar otomatis mengembalikan kuota.
     */
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique(); // selalu huruf besar, lihat mutator model
            $table->string('name');
            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 14, 2);
            $table->decimal('max_discount', 14, 2)->nullable();
            $table->decimal('min_subtotal', 14, 2)->default(0);

            $table->unsignedInteger('usage_limit')->nullable();    // null = tanpa batas
            $table->unsignedInteger('per_user_limit')->nullable(); // null = tanpa batas

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
