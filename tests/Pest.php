<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Mengarahkan `public_path()` ke folder sementara, supaya test unggah foto
 * produk (yang dipindah ke `public/assets/images/`) tidak mengotori folder
 * public asli. Hapus folder yang dikembalikan di `afterEach`, dan panggil
 * `$this->withoutVite()` karena manifest Vite juga dicari di public_path().
 */
function fakePublicPath(): string
{
    $path = storage_path('framework/testing/public-'.Str::random(10));

    File::ensureDirectoryExists($path);
    app()->usePublicPath($path);

    return $path;
}

/**
 * Pesanan pembeli berisi 1 barang Rp100.000 (stok awal 5) yang sudah dibayar
 * tapi belum diproses penjual — titik awal untuk "Ubah Status" dan notifikasi.
 *
 * @return array{order: Order, package: StoreOrder, variant: ProductVariant}
 */
function paidOrderFor(User $buyer): array
{
    $variant = ProductVariant::factory()->create(['stock' => 5, 'price' => 100000]);
    Cart::forUser($buyer)->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $order = Order::placeFromCart($buyer, [
        'recipient_name' => 'Zahir',
        'recipient_phone' => '081200000000',
        'street' => 'Jl. Merdeka No. 1',
        'city' => 'Bandung',
        'state' => 'Jawa Barat',
        'postal_code' => '40111',
    ], 'jne-reg', 'qris');

    $payment = $order->payments()->sole();
    $payment->settle(Payment::SIMULATOR.'-'.$payment->reference);

    return ['order' => $order->refresh(), 'package' => $order->storeOrders()->sole(), 'variant' => $variant];
}

/**
 * Seperti `paidOrderFor()`, tapi paketnya sudah dikemas dan dikirim dengan
 * resi JNE0001 — titik awal untuk ulasan, komplain, dan pelacakan.
 *
 * @return array{order: Order, package: StoreOrder, variant: ProductVariant}
 */
function shippedOrderFor(User $buyer): array
{
    ['order' => $order, 'package' => $package, 'variant' => $variant] = paidOrderFor($buyer);

    $package->process();
    $package->ship('JNE0001');

    return ['order' => $order->refresh(), 'package' => $package->refresh(), 'variant' => $variant];
}

/**
 * Memuat satu `@island(lazy: true)` seperti yang dilakukan browser saat island
 * terlihat, lalu mengembalikan HTML island tersebut. Livewire belum punya
 * helper test untuk island, dan `withoutLazyLoading()` tidak berlaku untuknya.
 */
function loadIsland(Testable $component, string $name): string
{
    $component->update(calls: [[
        'method' => '__lazyLoadIsland',
        'params' => [],
        'path' => '',
        'metadata' => ['island' => ['name' => $name, 'mode' => 'morph']],
    ]]);

    return implode('', $component->effects['islandFragments'] ?? []);
}
