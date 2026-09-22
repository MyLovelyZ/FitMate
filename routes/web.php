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
