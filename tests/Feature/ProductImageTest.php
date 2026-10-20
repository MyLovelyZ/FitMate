<?php

/**
 * Foto produk: satu gambar utama per produk, foto demo dari seeder, dan foto
 * yang tampil di katalog, detail produk, keranjang, serta tabel produk seller.
 */

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('menjadikan foto pertama produk sebagai gambar utama', function () {
    $product = Product::factory()->create();

    $first = ProductImage::factory()->for($product)->create();
    $second = ProductImage::factory()->for($product)->create(['sort_order' => 1]);

    expect($first->fresh()->is_primary)->toBeTrue()
        ->and($second->fresh()->is_primary)->toBeFalse()
        ->and($product->primaryImage->is($first))->toBeTrue();
});

it('hanya menyisakan satu gambar utama per produk', function () {
    $product = Product::factory()->create();
    $old = ProductImage::factory()->for($product)->create();
    $otherProductImage = ProductImage::factory()->create();

    $new = ProductImage::factory()->for($product)->primary()->create(['sort_order' => 1]);

    expect($old->fresh()->is_primary)->toBeFalse()
        ->and($new->fresh()->is_primary)->toBeTrue()
        ->and($otherProductImage->fresh()->is_primary)->toBeTrue()
        ->and($product->images->first()->is($new))->toBeTrue();
});

it('membentuk alamat foto dari path yang relatif ke public', function () {
    $image = ProductImage::factory()->create(['path' => 'images/products/kaos-katun-basic-1.webp']);

    expect($image->url)->toBe(asset('images/products/kaos-katun-basic-1.webp'));
});

it('memberi setiap produk demo foto yang filenya ada, dan aman di-seed ulang', function () {
    $this->seed();

    $products = Product::with('images')->get();

    expect($products)->not->toBeEmpty();

    foreach ($products as $product) {
        expect($product->images)->not->toBeEmpty()
            ->and($product->images->where('is_primary', true))->toHaveCount(1)
            ->and($product->images->first()->path)->toBe("images/products/{$product->slug}-1.webp");

        foreach ($product->images as $image) {
            expect(public_path($image->path))->toBeFile();
        }
    }

    $imageCount = ProductImage::count();

    $this->seed(ProductSeeder::class);

    expect(ProductImage::count())->toBe($imageCount)
        ->and(ProductImage::where('is_primary', true)->count())->toBe($products->count());
});

it('menampilkan foto utama di kartu katalog dan foto kedua untuk efek sorot', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->for($product)->create();
    ProductImage::factory()->for($product)->create(['path' => 'images/products/depan.webp']);
    ProductImage::factory()->for($product)->create(['path' => 'images/products/belakang.webp', 'sort_order' => 1]);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSeeInOrder([asset('images/products/depan.webp'), asset('images/products/belakang.webp')], false);
});

it('tetap menampilkan kartu katalog untuk produk tanpa foto', function () {
    $product = Product::factory()->create(['name' => 'Topi Tanpa Foto']);
    ProductVariant::factory()->for($product)->create();

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('Topi Tanpa Foto')
        ->assertDontSee('images/products/', false);
});

it('menampilkan galeri detail produk dengan gambar utama di depan', function () {
    $product = Product::factory()->create();
    ProductImage::factory()->for($product)->create(['path' => 'images/products/samping.webp']);
    ProductImage::factory()->for($product)->primary()->create(['path' => 'images/products/utama.webp', 'sort_order' => 1]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('Lihat foto 2')
        ->assertSeeInOrder([
            asset('images/products/utama.webp'),
            asset('images/products/samping.webp'),
            asset('images/products/utama.webp'), // foto besar
        ], false);

    $component = Livewire::test('pages::detail-product', ['product' => $product])
        ->set('activeImage', 1);

    expect($component->instance()->activePhoto['url'])->toBe(asset('images/products/samping.webp'));
});

it('menyembunyikan strip thumbnail kalau produk belum punya foto', function () {
    $product = Product::factory()->create();

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertDontSee('Lihat foto')
        ->assertDontSee('images/products/', false);
});

it('menampilkan foto produk di keranjang pembeli', function () {
    $buyer = User::factory()->create();
    $item = CartItem::factory()->for(Cart::factory()->for($buyer))->create();
    $image = ProductImage::factory()->for($item->productVariant->product)->create();

    $this->actingAs($buyer)
        ->get(route('user.cart'))
        ->assertOk()
        ->assertSee($image->url, false);
});

it('menampilkan foto produk di tabel produk seller', function () {
    $store = Store::factory()->create();
    $image = ProductImage::factory()->for(Product::factory()->for($store))->create();

    $this->actingAs($store->owner)
        ->get(route('seller.products.index'))
        ->assertOk()
        ->assertSee($image->url, false);
});
