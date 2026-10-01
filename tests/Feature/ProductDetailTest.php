<?php

/**
 * Halaman detail produk: galeri, pemilihan ukuran, harga, dan ketersediaan.
 */

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Produk kaos dengan ukuran urut S -> M -> L dan satu warna.
 *
 * @param  array<string, int>  $stockPerSize  Kode ukuran => stok.
 */
function productWithSizes(array $stockPerSize = ['S' => 5, 'M' => 8, 'L' => 3]): Product
{
    $product = Product::factory()
        ->for(Store::factory()->create(['city' => 'Bandung']))
        ->create(['name' => 'Kaos Oversized Archive', 'base_price' => 159000]);

    $color = Color::factory()->create();
    $sortOrder = 0;

    foreach ($stockPerSize as $code => $stock) {
        $size = Size::factory()->create([
            'category_type_id' => $product->category->category_type_id,
            'name' => $code,
            'code' => $code,
            'sort_order' => ++$sortOrder,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'size_id' => $size->id,
            'color_id' => $color->id,
            'price' => null,
            'stock' => $stock,
        ]);
    }

    return $product;
}

/** Id ukuran milik produk uji, dicari lewat kodenya. */
function sizeId(string $code): int
{
    return Size::where('code', $code)->value('id');
}

it('menampilkan nama, toko, harga, dan deskripsi produk', function () {
    $product = productWithSizes();

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('Kaos Oversized Archive')
        ->assertSee($product->store->name)
        ->assertSee('Rp 159.000')
        ->assertSee($product->description)
        ->assertSee('Add to Bag')
        ->assertSee('Size Guide')
        ->assertSee('Shipping &amp; Returns', false)
        ->assertSee('Dikirim dari Bandung');
});

it('menampilkan semua ukuran urut dari terkecil', function () {
    $product = productWithSizes();

    Livewire::test('pages::detail-product', ['product' => $product])
        ->assertOk()
        ->assertSeeInOrder(['size-'.sizeId('S'), 'size-'.sizeId('M'), 'size-'.sizeId('L')])
        ->assertSet('selectedSizeId', sizeId('S'));
});

it('memilih ukuran pertama yang masih ada stoknya saat halaman dibuka', function () {
    $product = productWithSizes(['S' => 0, 'M' => 4, 'L' => 2]);

    Livewire::test('pages::detail-product', ['product' => $product])
        ->assertSet('selectedSizeId', sizeId('M'));
});

it('mengikuti harga varian saat pembeli mengganti ukuran', function () {
    $product = productWithSizes();

    ProductVariant::where('product_id', $product->id)
        ->where('size_id', sizeId('L'))
        ->update(['price' => 199000]);

    Livewire::test('pages::detail-product', ['product' => $product])
        ->assertSee('Rp 159.000')
        ->call('selectSize', sizeId('L'))
        ->assertSet('selectedSizeId', sizeId('L'))
        ->assertSee('Rp 199.000');
});

it('mengabaikan ukuran yang bukan milik produk ini', function () {
    $product = productWithSizes();
    $foreignSize = Size::factory()->create(['code' => 'XXL', 'sort_order' => 9]);

    Livewire::test('pages::detail-product', ['product' => $product])
        ->call('selectSize', $foreignSize->id)
        ->assertSet('selectedSizeId', sizeId('S'));
});

it('menandai produk habis ketika semua varian tidak ada stoknya', function () {
    $product = productWithSizes(['S' => 0, 'M' => 0, 'L' => 0]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('Sold Out')
        ->assertDontSee('Add to Bag');
});

it('tetap menampilkan harga dasar untuk produk tanpa ukuran', function () {
    $product = Product::factory()
        ->for(Store::factory()->create(['city' => 'Surabaya']))
        ->create(['base_price' => 279000]);

    ProductVariant::factory()->withoutSizing()->create([
        'product_id' => $product->id,
        'stock' => 6,
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('Rp 279.000')
        ->assertSee('Satu ukuran untuk semua')
        ->assertSee('Add to Bag')
        ->assertDontSee('Size Guide');
});

it('menjaga indeks foto tetap di dalam rentang galeri', function () {
    $product = productWithSizes();

    Livewire::test('pages::detail-product', ['product' => $product])
        ->set('activeImage', 99)
        ->assertOk()
        ->assertSee('Kaos Oversized Archive');
});

it('menolak produk yang tidak aktif atau sudah dihapus', function () {
    $product = productWithSizes();
    $product->update(['is_active' => false]);

    $this->get(route('products.show', $product))->assertNotFound();

    $product->update(['is_active' => true]);
    $product->delete();

    $this->get(route('products.show', $product))->assertNotFound();
});
