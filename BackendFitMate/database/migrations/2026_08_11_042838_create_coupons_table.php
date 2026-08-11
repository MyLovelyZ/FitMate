<?php

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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('cascade'); // null = kupon FitMate, berlaku semua toko
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->enum('type', ['percentage', 'fixed'])->default('fixed');
            $table->decimal('value', 12, 2);
            $table->decimal('min_purchase', 12, 2)->default(0); // minimal belanja biar kupon kepakai
            $table->decimal('max_discount', 12, 2)->nullable(); // batas potongan buat tipe percentage
            $table->unsignedInteger('usage_limit')->nullable(); // null = ngk dibatasi
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->date('expiry_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
