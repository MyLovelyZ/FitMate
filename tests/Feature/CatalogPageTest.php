<?php

/**
 * Halaman katalog (`products.index`): daftar produk, filter ukuran/warna/harga,
 * pengurutan, dan paginasi.
 */

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Bikin produk beserta satu varian aktifnya dalam satu langkah, karena semua
 * filter katalog bertumpu pada varian.
 */
function catalogProduct(string $name, int $price, ?Size $size = null, ?Color $color = null): Product
{
    $product = Product::factory()->create([
        'store_id' => Store::factory(),
        'name' => $name,
        'slug' => Str::slug($name),
        'base_price' => $price,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size_id' => $size?->id,
        'color_id' => $color?->id,
    ]);

    return $product;
}

it('hanya menampilkan produk yang aktif', function () {
    catalogProduct('Kaos Katun Basic', 129000);

    $hidden = Product::factory()->inactive()->create(['name' => 'Kemeja Arsip Lama']);
    ProductVariant::factory()->create(['product_id' => $hidden->id]);

    Livewire::test('pages::catalog')
        ->assertSee('Kaos Katun Basic')
        ->assertDontSee('Kemeja Arsip Lama');
});

it('menyaring produk berdasarkan ukuran', function () {
    $small = Size::factory()->create(['name' => 'S', 'code' => 'S']);
    $large = Size::factory()->create(['name' => 'L', 'code' => 'L']);

    catalogProduct('Hoodie Fleece Polos', 319000, $small);
    catalogProduct('Jaket Bomber Ringan', 449000, $large);

    Livewire::test('pages::catalog')
        ->call('toggleSize', $small->id)
        ->assertSee('Hoodie Fleece Polos')
        ->assertDontSee('Jaket Bomber Ringan');
});

it('menyaring produk berdasarkan warna', function () {
    $black = Color::factory()->create(['name' => 'Hitam', 'slug' => 'hitam']);
    $cream = Color::factory()->create(['name' => 'Krem', 'slug' => 'krem']);

    catalogProduct('Sweater Rajut Rib', 299000, null, $black);
    catalogProduct('Rok Plisket Midi', 289000, null, $cream);

    Livewire::test('pages::catalog')
        ->call('toggleColor', $black->id)
        ->assertSee('Sweater Rajut Rib')
        ->assertDontSee('Rok Plisket Midi');
});

it('mencocokkan ukuran dan warna pada varian yang sama', function () {
    $small = Size::factory()->create(['name' => 'S', 'code' => 'S']);
    $medium = Size::factory()->create(['name' => 'M', 'code' => 'M']);
    $black = Color::factory()->create(['name' => 'Hitam', 'slug' => 'hitam']);
    $cream = Color::factory()->create(['name' => 'Krem', 'slug' => 'krem']);

    catalogProduct('Kemeja Oxford Lengan Panjang', 259000, $small, $black);

    // Punya ukuran S dan warna hitam, tapi tidak pernah dalam satu varian.
    $mixed = catalogProduct('Kemeja Linen Santai', 289000, $small, $cream);
    ProductVariant::factory()->create([
        'product_id' => $mixed->id,
        'size_id' => $medium->id,
        'color_id' => $black->id,
    ]);

    Livewire::test('pages::catalog')
        ->call('toggleSize', $small->id)
        ->call('toggleColor', $black->id)
        ->assertSee('Kemeja Oxford Lengan Panjang')
        ->assertDontSee('Kemeja Linen Santai');
});

it('menyaring produk berdasarkan rentang harga setelah filter diterapkan', function () {
    catalogProduct('Topi Baseball Katun', 149000);
    catalogProduct('Sepatu Derby Kulit', 899000);

    Livewire::test('pages::catalog')
        ->set('minPrice', '200000')
        ->set('maxPrice', '1000000')
        ->call('applyFilters')
        ->assertSee('Sepatu Derby Kulit')
        ->assertDontSee('Topi Baseball Katun');
});

it('mengurutkan produk berdasarkan harga', function () {
    catalogProduct('Sepatu Derby Kulit', 899000);
    catalogProduct('Topi Baseball Katun', 149000);

    Livewire::test('pages::catalog')
        ->set('sort', 'price-asc')
        ->assertSeeInOrder(['Topi Baseball Katun', 'Sepatu Derby Kulit']);

    Livewire::test('pages::catalog')
        ->set('sort', 'price-desc')
        ->assertSeeInOrder(['Sepatu Derby Kulit', 'Topi Baseball Katun']);
});

it('mengabaikan urutan yang tidak dikenal dari query string', function () {
    Livewire::withQueryParams(['sort' => 'termurah-banget'])
        ->test('pages::catalog')
        ->assertSet('sort', 'newest');
});

it('membaca filter ukuran dari query string', function () {
    $small = Size::factory()->create(['name' => 'S', 'code' => 'S']);

    catalogProduct('Hoodie Fleece Polos', 319000, $small);
    catalogProduct('Jaket Bomber Ringan', 449000);

    Livewire::withQueryParams(['size' => [$small->id]])
        ->test('pages::catalog')
        ->assertSee('Hoodie Fleece Polos')
        ->assertDontSee('Jaket Bomber Ringan');
});

it('memecah katalog jadi 12 produk per halaman', function () {
    $size = Size::factory()->create(['name' => 'S', 'code' => 'S']);

    collect(range(1, 14))->each(fn (int $number) => catalogProduct(
        sprintf('Produk Arsip %02d', $number),
        100000 + $number,
        $size,
    ));

    // Urutan bawaan "Newest", jadi halaman pertama berisi produk 14 sampai 03.
    Livewire::test('pages::catalog')
        ->assertSee('Showing 14 Results')
        ->assertSee('Produk Arsip 14')
        ->assertDontSee('Produk Arsip 01')
        ->call('gotoPage', 2)
        ->assertSee('Produk Arsip 01')
        ->assertSee('Produk Arsip 02')
        ->assertDontSee('Produk Arsip 14');
});

it('kembali ke halaman pertama setiap kali filter berubah', function () {
    $size = Size::factory()->create(['name' => 'S', 'code' => 'S']);

    collect(range(1, 14))->each(fn (int $number) => catalogProduct(
        sprintf('Produk Arsip %02d', $number),
        100000 + $number,
        $size,
    ));

    Livewire::test('pages::catalog')
        ->call('gotoPage', 2)
        ->assertSee('Produk Arsip 01')
        ->call('toggleSize', $size->id)
        ->assertSee('Produk Arsip 14')
        ->assertDontSee('Produk Arsip 01');
});

it('menghapus semua filter lewat tombol reset', function () {
    $small = Size::factory()->create(['name' => 'S', 'code' => 'S']);
    $black = Color::factory()->create(['name' => 'Hitam', 'slug' => 'hitam']);

    Livewire::test('pages::catalog')
        ->call('toggleSize', $small->id)
        ->call('toggleColor', $black->id)
        ->set('minPrice', '100000')
        ->call('resetFilters')
        ->assertSet('sizeIds', [])
        ->assertSet('colorIds', [])
        ->assertSet('minPrice', '');
});

it('menampilkan pesan kosong saat tidak ada produk yang cocok', function () {
    catalogProduct('Topi Baseball Katun', 149000);

    Livewire::test('pages::catalog')
        ->set('minPrice', '5000000')
        ->call('applyFilters')
        ->assertSee('Tidak ada produk yang cocok')
        ->assertDontSee('Topi Baseball Katun');
});

it('menautkan tiap kartu ke halaman detail produknya', function () {
    $product = catalogProduct('Kaos Oversized Archive', 159000);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee(route('products.show', $product), escape: false);
});
