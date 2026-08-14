<?php

/**
 * BE-090: audit otorisasi.
 *
 * Tiga janji yang diperiksa di sini:
 * - seller hanya bisa menyentuh datanya sendiri
 * - pembeli hanya bisa melihat pesanannya sendiri
 * - hanya admin yang bisa mengubah standar ukuran
 */

use App\Enums\Gender;
use App\Enums\SizeType;
use App\Models\Order;
use App\Models\Product;
use App\Models\SizeChart;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\User;
use Database\Seeders\BodyMeasurementSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SizeChartSeeder;

it('menolak tamu di seluruh halaman yang butuh masuk', function (string $name) {
    $this->get(route($name))->assertRedirect(route('login'));
})->with([
    'cart.index',
    'wishlist.index',
    'orders.index',
    'profile.edit',
    'profile.body',
    'profile.addresses',
    'checkout.index',
]);

it('menolak pembeli biasa masuk ke area admin', function (string $name) {
    $this->actingAs(User::factory()->create())->get(route($name))->assertForbidden();
})->with([
    'admin.dashboard',
    'admin.size-charts.index',
    'admin.categories.index',
    'admin.moderation.index',
]);

it('melarang non-admin mengubah standar ukuran', function () {
    $this->seed([BodyMeasurementSeeder::class, SizeChartSeeder::class]);

    $chart = SizeChart::first();
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->patch(route('admin.size-charts.update', $chart), ['name' => 'Diubah Diam-diam'])
        ->assertForbidden();

    expect($chart->fresh()->name)->not->toBe('Diubah Diam-diam');
});

it('mengizinkan admin mengubah standar ukuran', function () {
    $this->seed([BodyMeasurementSeeder::class, SizeChartSeeder::class]);

    $chart = SizeChart::first();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.size-charts.update', $chart), ['name' => 'Atasan Pria Revisi', 'is_active' => 1])
        ->assertRedirect();

    expect($chart->fresh()->name)->toBe('Atasan Pria Revisi');
});

it('melarang seller mengubah produk toko lain', function () {
    $this->seed([BodyMeasurementSeeder::class, CategorySeeder::class, SizeChartSeeder::class]);

    $intruder = User::factory()->seller()->create();
    Store::factory()->active()->for($intruder, 'owner')->create();

    $otherProduct = Product::factory()->active()->create([
        'size_chart_id' => SizeChart::firstWhere(['size_type' => SizeType::Top, 'gender' => Gender::Male])->id,
    ]);

    $this->actingAs($intruder)
        ->get(route('seller.products.edit', $otherProduct))
        ->assertForbidden();
});

it('tidak menampilkan pesanan toko lain di daftar pesanan seller', function () {
    $seller = User::factory()->seller()->create();
    $store = Store::factory()->active()->for($seller, 'owner')->create();

    $ownOrder = StoreOrder::factory()->for($store)->create();
    $foreignOrder = StoreOrder::factory()->create();

    $this->actingAs($seller)
        ->get(route('seller.orders.index'))
        ->assertOk()
        ->assertSee($ownOrder->store_order_number)
        ->assertDontSee($foreignOrder->store_order_number);

    $this->actingAs($seller)
        ->get(route('seller.orders.show', $foreignOrder))
        ->assertForbidden();
});

it('melarang pembeli melihat pesanan orang lain', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orders.show', $order))
        ->assertForbidden();
});

it('melarang pembeli menyentuh keranjang orang lain', function () {
    $this->seed([BodyMeasurementSeeder::class, CategorySeeder::class, SizeChartSeeder::class]);

    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $cart = $owner->currentCart();
    $item = $cart->items()->create([
        'product_variant_id' => variantFor()->id,
        'quantity' => 1,
    ]);

    $this->actingAs($intruder)
        ->delete(route('cart.destroy', $item))
        ->assertForbidden();

    expect($item->fresh())->not->toBeNull();
});
