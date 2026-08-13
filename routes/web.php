<?php

use Illuminate\Support\Facades\Route;

/**
 * Route sementara untuk kerangka tampilan.
 *
 * Semua route di bawah ini hanya menampilkan view kosong supaya struktur
 * `resources/views` bisa dibuka di browser sejak sekarang. Ganti satu per satu
 * dengan controller sungguhan sesuai urutan di `todo.md`.
 */
$belumDikerjakan = fn () => abort(501, 'Belum diimplementasikan. Lihat todo.md.');

Route::view('/', 'home')->name('home');

Route::view('/login', 'auth.login')->name('login');
Route::view('/register', 'auth.register')->name('register');
Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
Route::view('/reset-password/{token}', 'auth.reset-password')->name('password.reset');
Route::post('/login', $belumDikerjakan);
Route::post('/register', $belumDikerjakan);
Route::post('/forgot-password', $belumDikerjakan)->name('password.email');
Route::post('/reset-password', $belumDikerjakan)->name('password.update');
Route::post('/logout', $belumDikerjakan)->name('logout');

Route::view('/products', 'products.index')->name('products.index');
Route::view('/products/{product}', 'products.show')->name('products.show');

Route::view('/cart', 'cart.index')->name('cart.index');
Route::view('/wishlist', 'wishlist.index')->name('wishlist.index');

Route::view('/checkout', 'checkout.index')->name('checkout.index');
Route::view('/checkout/success', 'checkout.success')->name('checkout.success');
Route::post('/checkout', $belumDikerjakan);

Route::view('/orders', 'orders.index')->name('orders.index');
Route::view('/orders/{order}', 'orders.show')->name('orders.show');

Route::view('/profile', 'profile.edit')->name('profile.edit');
Route::view('/profile/body', 'profile.body-profile')->name('profile.body');
Route::view('/profile/addresses', 'profile.addresses')->name('profile.addresses');
Route::patch('/profile', $belumDikerjakan);
Route::post('/profile/body', $belumDikerjakan);
Route::post('/profile/addresses', $belumDikerjakan);

Route::prefix('seller')->name('seller.')->group(function () {
    Route::view('/', 'seller.dashboard')->name('dashboard');
    Route::view('/products', 'seller.products.index')->name('products.index');
    Route::view('/products/create', 'seller.products.form')->name('products.create');
    Route::view('/orders', 'seller.orders.index')->name('orders.index');
    Route::view('/store', 'seller.store.edit')->name('store.edit');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');
    Route::view('/size-charts', 'admin.size-charts.index')->name('size-charts.index');
    Route::view('/categories', 'admin.categories.index')->name('categories.index');
});
