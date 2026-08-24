<?php

/**
 * Memastikan setiap halaman masih bisa dirender.
 *
 * Test ini menangkap kesalahan yang tidak terlihat saat Blade dikompilasi:
 * nama route yang salah, komponen yang tidak ada, atau prop wajib yang lupa diisi.
 * Tambahkan nama route baru ke daftar di bawah setiap kali membuat halaman baru.
 */
it('bisa merender semua halaman', function (string $name) {
    $this->get(route($name))->assertOk();
})->with([
    'home',
    'login',
    'register',
]);
