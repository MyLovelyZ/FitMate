<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            // Satu seller satu toko. Kalau nanti boleh punya banyak, cukup lepas unique-nya.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();
            $table->string('phone_number', 32)->nullable();
            $table->string('email')->nullable();
            // Alamat asal pengiriman, dipakai buat hitung ongkir.
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 10)->nullable();
            // Toko mulai dari 'pending': sebagai pihak ketiga yang menahan uang pembeli,
            // penjual wajib diverifikasi dulu sebelum boleh berjualan.
            $table->enum('status', ['pending', 'active', 'suspended', 'rejected'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->decimal('rating_average', 3, 2)->default(0); // cache dari reviews
            $table->unsignedInteger('rating_count')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
