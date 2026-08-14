<?php

/**
 * Memastikan setiap halaman di `resources/views` masih bisa dirender.
 *
 * Test ini menangkap kesalahan yang tidak terlihat saat Blade dikompilasi:
 * nama route yang salah, komponen yang tidak ada, prop wajib yang lupa diisi,
 * atau variabel yang lupa dikirim controller.
 *
 * Tambahkan nama route baru ke daftar yang sesuai setiap kali membuat halaman.
 */

use App\Models\Order;
use App\Models\Product;
use App\Models\SizeChart;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\User;
use Database\Seeders\BodyMeasurementSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SizeChartSeeder;

it('bisa merender halaman publik', function (string $name) {
    $this->get(route($name, ['token' => 'contoh']))->assertOk();
})->with([
    'home',
    'login',
    'register',
    'password.request',
    'password.reset',
    'products.index',
]);

it('bisa merender halaman detail produk', function () {
    $this->seed([BodyMeasurementSeeder::class, CategorySeeder::class, SizeChartSeeder::class]);

    $product = Product::factory()->active()->create([
        'size_chart_id' => SizeChart::first()->id,
    ]);

    $this->get(route('products.show', $product))->assertOk();
});

it('bisa merender halaman pembeli', function (string $name) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route($name))->assertOk();
})->with([
    'cart.index',
    'wishlist.index',
    'orders.index',
    'profile.edit',
    'profile.body',
    'profile.addresses',
]);

it('bisa merender halaman detail pesanan', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    StoreOrder::factory()->for($order)->create();

    $this->actingAs($user)->get(route('orders.show', $order))->assertOk();
    $this->actingAs($user)->get(route('checkout.success', $order))->assertOk();
});

it('bisa merender halaman penjual', function (string $name) {
    $this->seed([BodyMeasurementSeeder::class, CategorySeeder::class, SizeChartSeeder::class]);

    $seller = User::factory()->seller()->create();
    Store::factory()->active()->for($seller, 'owner')->create();

    $this->actingAs($seller)->get(route($name))->assertOk();
})->with([
    'seller.dashboard',
    'seller.products.index',
    'seller.products.create',
    'seller.orders.index',
    'seller.store.edit',
]);

it('bisa merender halaman admin', function (string $name) {
    $this->seed([BodyMeasurementSeeder::class, CategorySeeder::class, SizeChartSeeder::class]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route($name))->assertOk();
})->with([
    'admin.dashboard',
    'admin.size-charts.index',
    'admin.categories.index',
    'admin.moderation.index',
]);

it('bisa merender halaman rincian standar ukuran', function () {
    $this->seed([BodyMeasurementSeeder::class, SizeChartSeeder::class]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.size-charts.show', SizeChart::first()))->assertOk();
});
