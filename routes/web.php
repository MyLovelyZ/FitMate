<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\SizeChartController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BodyProfileController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Seller\StoreController as SellerStoreController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman Publik
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

/*
|--------------------------------------------------------------------------
| Autentikasi
|--------------------------------------------------------------------------
|
| Login, register, dan lupa kata sandi diberi rate limit karena ketiganya
| adalah pintu masuk yang paling sering dicoba paksa (BE-091).
|
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Area Pembeli
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');

    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/{order}/success', [CheckoutController::class, 'success'])->name('checkout.success');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    Route::post('/order-items/{orderItem}/review', [ProductReviewController::class, 'store'])->name('reviews.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/profile/body', [BodyProfileController::class, 'index'])->name('profile.body');
    Route::post('/profile/body', [BodyProfileController::class, 'store'])->name('profile.body.store');
    Route::patch('/profile/body/{bodyProfile}', [BodyProfileController::class, 'update'])->name('profile.body.update');
    Route::delete('/profile/body/{bodyProfile}', [BodyProfileController::class, 'destroy'])->name('profile.body.destroy');

    Route::get('/profile/addresses', [AddressController::class, 'index'])->name('profile.addresses');
    Route::post('/profile/addresses', [AddressController::class, 'store'])->name('profile.addresses.store');
    Route::patch('/profile/addresses/{address}', [AddressController::class, 'update'])->name('profile.addresses.update');
    Route::post('/profile/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('profile.addresses.default');
    Route::delete('/profile/addresses/{address}', [AddressController::class, 'destroy'])->name('profile.addresses.destroy');
});

/*
|--------------------------------------------------------------------------
| Area Penjual
|--------------------------------------------------------------------------
|
| `store.edit` dan `store.update` sengaja berada di luar middleware
| `store` — keduanya justru halaman tempat toko dibuat pertama kali.
|
*/

Route::prefix('seller')->name('seller.')->middleware('auth')->group(function (): void {
    Route::get('/store', [SellerStoreController::class, 'edit'])->name('store.edit');
    Route::patch('/store', [SellerStoreController::class, 'update'])->name('store.update');

    Route::middleware(['role:seller,admin,superadmin', 'store'])->group(function (): void {
        Route::get('/', SellerDashboardController::class)->name('dashboard');

        Route::get('/products', [SellerProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [SellerProductController::class, 'create'])->name('products.create');
        Route::post('/products', [SellerProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [SellerProductController::class, 'edit'])->name('products.edit');
        Route::patch('/products/{product}', [SellerProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [SellerProductController::class, 'destroy'])->name('products.destroy');

        Route::get('/orders', [SellerOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{storeOrder}', [SellerOrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{storeOrder}/status', [SellerOrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('/orders/{storeOrder}/ship', [SellerOrderController::class, 'ship'])->name('orders.ship');
    });
});

/*
|--------------------------------------------------------------------------
| Area Admin
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin,superadmin'])->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('/size-charts', [SizeChartController::class, 'index'])->name('size-charts.index');
    Route::post('/size-charts', [SizeChartController::class, 'store'])->name('size-charts.store');
    Route::get('/size-charts/{sizeChart}', [SizeChartController::class, 'show'])->name('size-charts.show');
    Route::patch('/size-charts/{sizeChart}', [SizeChartController::class, 'update'])->name('size-charts.update');
    Route::delete('/size-charts/{sizeChart}', [SizeChartController::class, 'destroy'])->name('size-charts.destroy');
    Route::post('/size-charts/{sizeChart}/entries', [SizeChartController::class, 'storeEntry'])->name('size-charts.entries.store');
    Route::delete('/size-charts/{sizeChart}/entries/{entry}', [SizeChartController::class, 'destroyEntry'])->name('size-charts.entries.destroy');

    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::patch('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('/brands', [AdminCategoryController::class, 'storeBrand'])->name('brands.store');
    Route::delete('/brands/{brand}', [AdminCategoryController::class, 'destroyBrand'])->name('brands.destroy');

    Route::get('/moderation', [ModerationController::class, 'index'])->name('moderation.index');
    Route::patch('/moderation/stores/{store}', [ModerationController::class, 'verifyStore'])->name('moderation.stores');
    Route::patch('/moderation/products/{product}', [ModerationController::class, 'reviewProduct'])->name('moderation.products');
});
