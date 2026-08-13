<?php

/**
 * Memastikan setiap halaman di `resources/views` masih bisa dirender.
 *
 * Test ini menangkap kesalahan yang tidak terlihat saat Blade dikompilasi:
 * nama route yang salah, komponen yang tidak ada, atau prop wajib yang lupa diisi.
 * Tambahkan nama route baru ke daftar di bawah setiap kali membuat halaman baru.
 */
it('bisa merender semua halaman kerangka', function (string $name) {
    $this->get(route($name, ['product' => 1, 'order' => 1, 'token' => 'contoh']))
        ->assertOk();
})->with([
    'home',
    'login',
    'register',
    'password.request',
    'password.reset',
    'products.index',
    'products.show',
    'cart.index',
    'wishlist.index',
    'checkout.index',
    'checkout.success',
    'orders.index',
    'orders.show',
    'profile.edit',
    'profile.body',
    'profile.addresses',
    'seller.dashboard',
    'seller.products.index',
    'seller.products.create',
    'seller.orders.index',
    'seller.store.edit',
    'admin.dashboard',
    'admin.size-charts.index',
    'admin.categories.index',
]);
