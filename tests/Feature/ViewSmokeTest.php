<?php

/**
 * Memastikan setiap halaman masih bisa dirender.
 *
 * Test ini menangkap kesalahan yang tidak terlihat saat Blade dikompilasi:
 * nama route yang salah, komponen yang tidak ada, atau prop wajib yang lupa diisi.
 * Tambahkan nama route baru ke daftar di bawah setiap kali membuat halaman baru.
 */

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('bisa merender semua halaman', function (string $name) {
    $this->get(route($name))->assertOk();
})->with([
    'home',
    'products.index',
    'login',
    'register',
]);

it('bisa merender halaman yang butuh parameter route', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id]);

    $this->get(route('products.show', $product))->assertOk();
});
