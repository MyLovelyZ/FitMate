<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\StoreIdentityCardController;
use Illuminate\Support\Facades\Route;

/**
 * Halaman dirender langsung oleh komponen Livewire full-page (`pages::*`).
 * Controller hanya dipakai untuk aksi yang bukan halaman, seperti logout.
 */
Route::livewire('/', 'pages::home')->name('home');

Route::livewire('/produk', 'pages::catalog')->name('products.index');
Route::livewire('/produk/{product:slug}', 'pages::detail-product')->name('products.show');

// FAQ & kontak; tujuan link "FAQ" dan "Contact Us" di footer.
Route::livewire('/bantuan', 'pages::help')->name('help');

// Halaman publik tiap toko; hanya toko yang sedang berjualan.
Route::livewire('/toko/{store:slug}', 'pages::store-show')->name('stores.show');

// Checkout memakai keranjang, jadi hanya untuk akun yang boleh belanja (alias `user`).
Route::middleware(['auth', 'user'])->group(function () {
    Route::livewire('/checkout', 'pages::checkout')->name('checkout');
    Route::livewire('/checkout/success/{order:order_number}', 'pages::checkout-success')->name('checkout.success');
    // Nota pembeli; status & warnanya ikut berubah saat admin/seller mengubah pesanan.
    Route::livewire('/berhasil/{order:order_number}', 'pages::berhasil')->name('orders.invoice');
});

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::login')->name('login');
    Route::livewire('/register', 'pages::register')->name('register');
    // Nama `password.reset` dipakai notifikasi reset bawaan Laravel untuk membuat link di email.
    Route::livewire('/lupa-password', 'pages::forgot-password')->name('password.request');
    Route::livewire('/reset-password/{token}', 'pages::reset-password')->name('password.reset');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Profil dipakai semua role; tab ukuran badan & alamat hanya muncul untuk pembeli.
// Pengajuan toko oleh pembeli (KEP-5). Halaman perkenalannya publik; formulir bertahap,
// status review, dan profil toko setelah disetujui butuh login (dicek di komponennya).
Route::livewire('/buka-toko', 'pages::store-apply')->name('stores.create');

Route::livewire('/profil', 'pages::profile')
    ->middleware('auth')
    ->name('profile');

/**
 * Area per role. Middleware role ada di `app/Http/Middleware` dan alias-nya
 * didaftarkan di `bootstrap/app.php`.
 */
// Dipakai bersama admin dan superadmin. Cakupannya beda per role (lihat `User::canModerate()`):
// admin hanya mengatur pelanggan & seller, superadmin semua akun kecuali sesama superadmin.
Route::middleware(['auth', 'admin'])->prefix('admin')->as('admin.')->group(function () {
    Route::livewire('/dashboard', 'pages::admin-dashboard')->name('dashboard');
    Route::livewire('/stores', 'pages::admin-stores')->name('stores.index');
    Route::livewire('/stores/{store}', 'pages::admin-store-review')->name('stores.show');
    // Foto KTP ada di disk privat, jadi dialirkan lewat controller khusus admin.
    Route::get('/stores/{store}/identity-card', StoreIdentityCardController::class)->name('stores.identity-card');
    Route::livewire('/users', 'pages::admin-users')->name('users.index');
    Route::livewire('/users/{user}', 'pages::admin-user-detail')->name('users.show');
    // Semua produk dari semua toko, termasuk yang nonaktif, diblokir, dan dihapus.
    Route::livewire('/products', 'pages::admin-products')->name('products.index');
    // Tambah produk oleh admin atas nama toko: form multipart → AdminController@storeProduct.
    Route::get('/products/create', [AdminController::class, 'createProduct'])->name('products.create');
    Route::post('/products', [AdminController::class, 'storeProduct'])->name('products.store');
    Route::livewire('/vouchers', 'pages::admin-vouchers')->name('vouchers.index');
    // Back-office transaksi: semua pesanan masuk dari semua toko + tombol ubah status.
    Route::get('/transactions', [AdminController::class, 'transactions'])->name('transactions.index');
    Route::post('/transactions/{order}/status', [AdminController::class, 'updateStatus'])->name('transactions.status');
});

Route::middleware(['auth', 'cs'])->prefix('cs')->as('cs.')->group(function () {
    Route::livewire('/dashboard', 'pages::cs-dashboard')->name('dashboard');
    Route::livewire('/chats', 'pages::cs-chats')->name('chats.index');
    Route::livewire('/tickets', 'pages::cs-tickets')->name('tickets.index');
    Route::livewire('/tickets/{dispute}', 'pages::cs-ticket-detail')->name('tickets.show');
});

Route::middleware(['auth', 'seller'])->prefix('seller')->as('seller.')->group(function () {
    Route::livewire('/dashboard', 'pages::seller-dashboard')->name('dashboard');
    Route::livewire('/products', 'pages::seller-products')->name('products.index');
    // Formulir produk = halaman Livewire; kirimannya (multipart, ada file foto) ke controller.
    Route::livewire('/products/create', 'pages::seller-product-form')->name('products.create');
    Route::post('/products', [SellerProductController::class, 'store'])->name('products.store');
    Route::livewire('/products/{product:slug}/edit', 'pages::seller-product-form')->name('products.edit');
    Route::put('/products/{product:slug}', [SellerProductController::class, 'update'])->name('products.update');
    Route::livewire('/orders', 'pages::seller-orders')->name('orders.index');
    Route::livewire('/orders/{storeOrder:store_order_number}', 'pages::seller-order-detail')->name('orders.show');
});

// Superadmin bekerja di area admin dengan cakupan penuh; yang khusus superadmin hanya pengaturan.
Route::middleware(['auth', 'superadmin'])->prefix('super-admin')->as('superadmin.')->group(function () {
    Route::redirect('/dashboard', '/admin/users')->name('dashboard');
    Route::livewire('/settings', 'pages::superadmin-settings')->name('settings');
});

Route::middleware(['auth', 'user'])->prefix('user')->as('user.')->group(function () {
    Route::livewire('/orders', 'pages::user-orders')->name('orders');
    Route::livewire('/orders/{order:order_number}', 'pages::user-order-detail')->name('orders.show');
    Route::livewire('/cart', 'pages::user-cart')->name('cart');
    Route::livewire('/wishlist', 'pages::user-wishlist')->name('wishlist');
    Route::livewire('/chat', 'pages::user-chat')->name('chat');
});
