<?php

/**
 * Halaman katalog (`products.index`): daftar produk, filter ukuran/warna/harga,
 * pengurutan, dan paginasi.
 */

use App\Models\Category;
use App\Models\CategoryType;
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
function catalogProduct(string $name, int $price, ?Size $size = null, ?Color $color = null, array $attributes = []): Product
{
    $product = Product::factory()->create([
        'store_id' => Store::factory(),
        'name' => $name,
        'slug' => Str::slug($name),
        'base_price' => $price,
        ...$attributes,
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

it('menyaring produk pria dan wanita, dengan produk unisex ikut di keduanya', function () {
    catalogProduct('Kemeja Oxford Lengan Panjang', 259000, attributes: ['gender' => 'men']);
    catalogProduct('Rok Plisket Midi', 289000, attributes: ['gender' => 'women']);
    catalogProduct('Hoodie Fleece Polos', 319000, attributes: ['gender' => 'unisex']);

    Livewire::withQueryParams(['gender' => 'men'])
        ->test('pages::catalog')
        ->assertSee('Kemeja Oxford Lengan Panjang')
        ->assertSee('Hoodie Fleece Polos')
        ->assertDontSee('Rok Plisket Midi');

    Livewire::test('pages::catalog')
        ->call('setFilter', 'gender', 'women')
        ->assertSee('Rok Plisket Midi')
        ->assertSee('Hoodie Fleece Polos')
        ->assertDontSee('Kemeja Oxford Lengan Panjang');
});

it('menyaring produk berdasarkan jenis kategori, mis. aksesoris', function () {
    $accessories = CategoryType::factory()->withoutSizes()->create(['name' => 'Aksesoris', 'slug' => 'aksesoris']);

    catalogProduct('Topi Baseball Katun', 149000, attributes: [
        'category_id' => Category::factory()->for($accessories),
    ]);
    catalogProduct('Kaos Katun Basic', 129000);

    Livewire::withQueryParams(['type' => 'aksesoris'])
        ->test('pages::catalog')
        ->assertSee('Topi Baseball Katun')
        ->assertDontSee('Kaos Katun Basic');
});

it('menampilkan hanya produk diskon di menu On Sale', function () {
    catalogProduct('Jaket Bomber Ringan', 449000, attributes: ['compare_at_price' => 559000]);
    catalogProduct('Jaket Denim Trucker', 529000);

    Livewire::withQueryParams(['collection' => 'sale'])
        ->test('pages::catalog')
        ->assertSee('Jaket Bomber Ringan')
        ->assertSee('Rp 559.000')
        ->assertDontSee('Jaket Denim Trucker');
});

it('menampilkan 12 produk terbaru di menu New Arrivals', function () {
    catalogProduct('Sepatu Derby Kulit', 899000, attributes: ['created_at' => now()->subDays(60)]);

    collect(range(1, 12))->each(fn (int $number) => catalogProduct(
        sprintf('Produk Baru %02d', $number),
        100000 + $number,
    ));

    Livewire::withQueryParams(['collection' => 'new'])
        ->test('pages::catalog')
        ->assertSee('Showing 12 Results')
        ->assertSee('Produk Baru 01')
        ->assertDontSee('Sepatu Derby Kulit');
});

it('melepas filter pilihan tunggal saat diklik lagi', function () {
    Livewire::test('pages::catalog')
        ->call('setFilter', 'gender', 'men')
        ->assertSet('gender', 'men')
        ->call('setFilter', 'gender', 'men')
        ->assertSet('gender', '');
});

it('mengabaikan nilai filter menu yang tidak dikenal', function () {
    Livewire::withQueryParams(['gender' => 'alien', 'type' => 'tidak-ada', 'collection' => 'gratis'])
        ->test('pages::catalog')
        ->assertSet('gender', '')
        ->assertSet('type', '')
        ->assertSet('collection', '')
        ->call('setFilter', 'sort', 'name')
        ->assertSet('sort', 'newest');
});

it('menautkan menu navbar ke katalog yang sudah tersaring', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('products.index', ['gender' => 'men']), escape: false)
        ->assertSee(route('products.index', ['gender' => 'women']), escape: false)
        ->assertSee(route('products.index', ['type' => 'aksesoris']), escape: false)
        ->assertSee(route('products.index', ['collection' => 'new']), escape: false)
        ->assertSee(route('products.index', ['collection' => 'sale']), escape: false);
});

it('menampilkan breadcrumbs sesuai menu yang dipilih', function () {
    $this->get(route('products.index'))
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSeeInOrder(['Beranda', 'Katalog']);

    $this->get(route('products.index', ['gender' => 'men']))
        ->assertSeeInOrder(['Beranda', 'Katalog', 'Men']);
});

it('mencari produk per kata, tidak harus berurutan', function () {
    catalogProduct('Kaos Katun Basic', 129000);
    catalogProduct('Kaos Oversized Archive', 159000);
    catalogProduct('Hoodie Fleece Polos', 319000);

    Livewire::withQueryParams(['q' => 'kaos basic'])
        ->test('pages::catalog')
        ->assertSee('Kaos Katun Basic')
        ->assertDontSee('Kaos Oversized Archive')
        ->assertDontSee('Hoodie Fleece Polos')
        ->assertSee('Showing 1 Result');
});

it('mencari lewat nama kategori dan jenis kategorinya', function () {
    $alasKaki = CategoryType::factory()->create(['name' => 'Alas Kaki', 'slug' => 'alas-kaki']);
    $sneakers = Category::factory()->for($alasKaki)->create(['name' => 'Sepatu Sneakers']);

    catalogProduct('Runner Mesh Ringan', 699000, attributes: ['category_id' => $sneakers->id]);
    catalogProduct('Topi Baseball Katun', 149000);

    Livewire::withQueryParams(['q' => 'sneakers'])
        ->test('pages::catalog')
        ->assertSee('Runner Mesh Ringan')
        ->assertDontSee('Topi Baseball Katun');

    Livewire::withQueryParams(['q' => 'alas kaki'])
        ->test('pages::catalog')
        ->assertSee('Runner Mesh Ringan')
        ->assertDontSee('Topi Baseball Katun');
});

it('menggabungkan pencarian dengan filter lain', function () {
    catalogProduct('Kaos Pria Basic', 129000, attributes: ['gender' => 'men']);
    catalogProduct('Kaos Wanita Crop', 139000, attributes: ['gender' => 'women']);

    Livewire::withQueryParams(['q' => 'kaos', 'gender' => 'women'])
        ->test('pages::catalog')
        ->assertSee('Kaos Wanita Crop')
        ->assertDontSee('Kaos Pria Basic')
        ->assertSee('“kaos” · Women');
});

it('merapikan kata kunci dari query string', function () {
    Livewire::withQueryParams(['q' => '   kaos   '])
        ->test('pages::catalog')
        ->assertSet('search', 'kaos');

    Livewire::withQueryParams(['q' => str_repeat('a', 300)])
        ->test('pages::catalog')
        ->assertSet('search', str_repeat('a', 100));
});

it('menampilkan saran kata kunci saat pencarian tidak menemukan apa pun', function () {
    catalogProduct('Kaos Katun Basic', 129000);

    Livewire::withQueryParams(['q' => 'jas hujan'])
        ->test('pages::catalog')
        ->assertSee('Tidak ada produk yang cocok')
        ->assertSee('Coba kata kunci lain')
        ->assertDontSee('Kaos Katun Basic');
});

it('menghapus pencarian lewat chip maupun tombol reset', function () {
    catalogProduct('Kaos Katun Basic', 129000);
    catalogProduct('Hoodie Fleece Polos', 319000);

    Livewire::withQueryParams(['q' => 'kaos'])
        ->test('pages::catalog')
        ->assertDontSee('Hoodie Fleece Polos')
        ->call('clearSearch')
        ->assertSet('search', '')
        ->assertSee('Hoodie Fleece Polos');

    Livewire::withQueryParams(['q' => 'kaos'])
        ->test('pages::catalog')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSee('Hoodie Fleece Polos');
});

it('menyediakan kolom pencarian di navbar yang mengarah ke katalog', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('role="search"', false)
        ->assertSee('action="'.route('products.index').'"', false)
        ->assertSee('name="q"', false)
        ->assertSee('aria-controls="siteSearch"', false);
});

it('membuka katalog hasil pencarian lewat URL dari navbar', function () {
    catalogProduct('Kaos Katun Basic', 129000);
    catalogProduct('Hoodie Fleece Polos', 319000);

    $this->get(route('products.index', ['q' => 'hoodie']))
        ->assertOk()
        ->assertSee('Hoodie Fleece Polos')
        ->assertDontSee('Kaos Katun Basic')
        ->assertSeeInOrder(['Beranda', 'Katalog', '“hoodie”']);
});
