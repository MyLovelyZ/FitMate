<?php

/**
 * Halaman publik tiap toko, visibilitas produk menurut status toko, dan seller
 * yang tetap bisa belanja di toko lain.
 */

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Produk aktif dengan satu varian berstok, milik `$store`.
 */
function storefrontProduct(Store $store, string $name): Product
{
    $product = Product::factory()->for($store)->create(['name' => $name]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 5]);

    return $product;
}

it('shows an active store with only its own products', function () {
    $store = Store::factory()->create(['name' => 'Archive Supply Co']);
    storefrontProduct($store, 'Kaos Milik Toko');
    storefrontProduct(Store::factory()->create(), 'Kaos Toko Lain');

    $this->get(route('stores.show', $store))
        ->assertOk()
        ->assertSee('Archive Supply Co')
        ->assertSee('Terverifikasi')
        ->assertSee('Kaos Milik Toko')
        ->assertDontSee('Kaos Toko Lain')
        ->assertSeeInOrder(['Beranda', 'Katalog', 'Archive Supply Co']);
});

it('searches and sorts within the store', function () {
    $store = Store::factory()->create();
    storefrontProduct($store, 'Hoodie Fleece');
    storefrontProduct($store, 'Kaos Basic');

    Livewire::test('pages::store-show', ['store' => $store])
        ->set('search', 'hoodie')
        ->assertSee('Hoodie Fleece')
        ->assertDontSee('Kaos Basic')
        ->set('search', '')
        ->set('sort', 'name')
        ->assertSeeInOrder(['Hoodie Fleece', 'Kaos Basic']);
});

it('hides stores that are not selling, except for the owner and admins', function (string $state) {
    $store = Store::factory()->{$state}()->create();

    $this->get(route('stores.show', $store))->assertNotFound();

    $this->actingAs($store->owner)
        ->get(route('stores.show', $store))
        ->assertOk()
        ->assertSee('Pratinjau');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('stores.show', $store))
        ->assertOk();
})->with(['pending', 'rejected', 'suspended']);

it('removes products of a suspended store from the catalog and product page', function () {
    $store = Store::factory()->suspended()->create();
    $hidden = storefrontProduct($store, 'Produk Toko Ditangguhkan');
    storefrontProduct(Store::factory()->create(), 'Produk Toko Aktif');

    Livewire::test('pages::catalog')
        ->assertSee('Produk Toko Aktif')
        ->assertDontSee('Produk Toko Ditangguhkan');

    $this->get(route('products.show', $hidden))->assertNotFound();
});

it('links the product page to its store', function () {
    $store = Store::factory()->create();
    $product = storefrontProduct($store, 'Kaos Berlink');

    $this->get(route('products.show', $product))
        ->assertSee(route('stores.show', $store), false);
});

it('lets a seller shop at other stores but not buy their own products', function () {
    $sellerStore = Store::factory()->create();
    $seller = $sellerStore->owner;
    $own = storefrontProduct($sellerStore, 'Produk Sendiri');
    $other = storefrontProduct(Store::factory()->create(), 'Produk Orang Lain');

    Livewire::actingAs($seller)
        ->test('pages::detail-product', ['product' => $other])
        ->call('addToCart', 1)
        ->assertHasNoErrors();

    Livewire::actingAs($seller)
        ->test('pages::detail-product', ['product' => $own])
        ->assertSee('Produk Tokomu Sendiri')
        ->call('addToCart', 1)
        ->assertHasErrors('cart');

    expect(CartItem::sole()->productVariant->product_id)->toBe($other->id);

    $this->actingAs($seller)->get(route('user.cart'))->assertOk();
});
