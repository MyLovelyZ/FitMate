<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/**
 * Halaman dirender langsung oleh komponen Livewire full-page (`pages::*`).
 * Controller hanya dipakai untuk aksi yang bukan halaman, seperti logout.
 */
Route::livewire('/', 'pages::home')->name('home');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::login')->name('login');
    Route::livewire('/register', 'pages::register')->name('register');
});

Route::post('/login', [LoginController::class, 'login'])->name('login.store');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.store');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');


    Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
});

Route::middleware(['auth', 'cs'])->prefix('cs')->as('cs.')->group(function () {
    // Dashboard CS / Tiket Bantuan / Live Chat Support
    Route::livewire('/dashboard', 'pages::cs.dashboard')->name('dashboard');
    Route::livewire('/chats', 'pages::cs.chats.index')->name('chats.index');
    Route::livewire('/tickets', 'pages::cs.tickets.index')->name('tickets.index');
});

Route::middleware(['auth', 'seller'])->prefix('seller')->as('seller. ')->group(function () {
    Route::livewire('/dashboard', 'pages::seller.dashboard')->name('dashboard');
    Route::livewire('/products', 'pages::seller.products.index')->name('products.index');
    Route::livewire('/orders', 'pages::seller.orders.index')->name('orders.index');
});

Route::middleware(['auth', 'superadmin'])->prefix('super-admin')->as('superadmin.')->group(function (){
    Route::livewire('/dashboard', 'pages::superadmin.users.index')->name('dashboard');
    Route::livewire('/users', 'pages::superadmin.users.index')->name('users.index');
    Route::livewire('/settings', 'pages::superadmin.settings')->name('settings');
});

Route::middleware(['auth', 'user'])->prefix('user')->as('user. ')->group(function (){
    Route::livewire('/profile', 'pages::user.profile')->name('name');
    Route::livewire('/orders', 'pages::user.orders')->name('user.orders');
    Route::livewire('cart', 'pages::user.cart')->name('user.cart');
});