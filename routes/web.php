<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/**
 * Halaman dirender langsung oleh komponen Livewire full-page (`pages::*`).
 * Controller hanya dipakai untuk aksi yang bukan halaman, seperti logout.
 */
Route::livewire('/', 'pages::home')->name('home');

Route::livewire('/produk', 'pages::catalog')->name('products.index');
Route::livewire('/produk/{product:slug}', 'pages::detail-product')->name('products.show');

Route::livewire('/checkout', 'pages::checkout')->name('checkout');
Route::livewire('/checkout/success', 'pages::checkout-success')->name('checkout.success');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::login')->name('login');
    Route::livewire('/register', 'pages::register')->name('register');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Profil dipakai semua role; tab ukuran badan & alamat hanya muncul untuk pembeli.
Route::livewire('/profil', 'pages::profile')
    ->middleware('auth')
    ->name('profile');

/**
 * Area per role. Middleware role ada di `app/Http/Middleware` dan alias-nya
 * didaftarkan di `bootstrap/app.php`.
 */
Route::middleware(['auth', 'admin'])->prefix('admin')->as('admin.')->group(function () {
    Route::livewire('/dashboard', 'pages::admin-dashboard')->name('dashboard');
});

Route::middleware(['auth', 'cs'])->prefix('cs')->as('cs.')->group(function () {
    Route::livewire('/dashboard', 'pages::cs-dashboard')->name('dashboard');
    Route::livewire('/chats', 'pages::cs-chats')->name('chats.index');
    Route::livewire('/tickets', 'pages::cs-tickets')->name('tickets.index');
});

Route::middleware(['auth', 'seller'])->prefix('seller')->as('seller.')->group(function () {
    Route::livewire('/dashboard', 'pages::seller-dashboard')->name('dashboard');
    Route::livewire('/products', 'pages::seller-products')->name('products.index');
    Route::livewire('/orders', 'pages::seller-orders')->name('orders.index');
});

Route::middleware(['auth', 'superadmin'])->prefix('super-admin')->as('superadmin.')->group(function () {
    Route::redirect('/dashboard', '/super-admin/users')->name('dashboard');
    Route::livewire('/users', 'pages::superadmin-users')->name('users.index');
    Route::livewire('/settings', 'pages::superadmin-settings')->name('settings');
});

Route::middleware(['auth', 'user'])->prefix('user')->as('user.')->group(function () {
    Route::livewire('/orders', 'pages::user-orders')->name('orders');
    Route::livewire('/orders/{order:order_number}', 'pages::user-order-detail')->name('orders.show');
    Route::livewire('/cart', 'pages::user-cart')->name('cart');
});
