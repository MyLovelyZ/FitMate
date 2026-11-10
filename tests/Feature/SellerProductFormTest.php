<?php

/**
 * Seller Center: formulir tambah/ubah produk (multipart/form-data ke
 * SellerProductController), unggah foto, validasi server, dan aksi di daftar
 * produk (tayang/sembunyikan, hapus).
 */

use App\Models\Category;
use App\Models\CategoryType;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->publicPath = fakePublicPath();
    $this->withoutVite();

    $this->seller = User::factory()->seller()->create();
    $this->store = Store::factory()->create(['user_id' => $this->seller->id]);

    $type = CategoryType::factory()->create(['has_sizes' => true]);
    $this->category = Category::factory()->create(['category_type_id' => $type->id, 'name' => 'Kemeja']);
    $this->sizeM = Size::factory()->create(['category_type_id' => $type->id, 'name' => 'M', 'code' => 'M']);
    $this->sizeL = Size::factory()->create(['category_type_id' => $type->id, 'name' => 'L', 'code' => 'L']);
    $this->color = Color::factory()->create(['name' => 'Hitam']);
});

afterEach(function () {
    File::deleteDirectory($this->publicPath);
});

/**
 * File yang sudah dipindah ke public/assets/images/.
 *
 * @return array<int, string>
 */
function uploadedProductFiles(): array
{
    return File::glob(public_path(Product::IMAGE_DIRECTORY.'/*'));
}

/**
 * Isian formulir yang sah: kemeja Rp150.000 dengan dua varian ukuran.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productForm(array $overrides = []): array
{
    return array_replace([
        'name' => 'Kemeja Linen Pendek',
        'category_id' => test()->category->id,
        'gender' => 'men',
        'description' => 'Linen adem untuk cuaca tropis.',
        'base_price' => '150000',
        'compare_at_price' => '',
        'is_active' => '1',
        'images' => [UploadedFile::fake()->image('depan.jpg', 900, 1125)],
        'variants' => [
            ['id' => '', 'size_id' => test()->sizeM->id, 'color_id' => test()->color->id, 'sku' => '', 'price' => '', 'stock' => '10'],
            ['id' => '', 'size_id' => test()->sizeL->id, 'color_id' => test()->color->id, 'sku' => 'kml-l', 'price' => '160000', 'stock' => '4'],
        ],
    ], $overrides);
}

it('renders the add product form as a multipart form posting to the controller', function () {
    $this->actingAs($this->seller)
        ->get(route('seller.products.create'))
        ->assertOk()
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('action="'.route('seller.products.store').'"', false)
        ->assertSee('name="images[]"', false)
        ->assertSee('Kemeja');
});

it('creates a product with variants and stores the uploaded photos on the public disk', function () {
    $this->actingAs($this->seller)
        ->post(route('seller.products.store'), productForm([
            'images' => [
                UploadedFile::fake()->image('depan.jpg', 900, 1125),
                UploadedFile::fake()->image('belakang.png', 900, 1125),
            ],
        ]))
        ->assertRedirect(route('seller.products.index'))
        ->assertSessionHas('notify')
        ->assertSessionHasNoErrors();

    $product = Product::sole();

    expect($product->store_id)->toBe($this->store->id)
        ->and($product->slug)->toBe('kemeja-linen-pendek')
        ->and((int) $product->base_price)->toBe(150000)
        ->and($product->compare_at_price)->toBeNull()
        ->and($product->is_active)->toBeTrue()
        ->and($product->variants)->toHaveCount(2);

    $medium = $product->variants->firstWhere('size_id', $this->sizeM->id);
    $large = $product->variants->firstWhere('size_id', $this->sizeL->id);

    expect($medium->sku)->toBe(sprintf('FM-%04d-M-%02d', $product->id, $this->color->id))
        ->and($medium->price)->toBeNull()
        ->and($medium->stock)->toBe(10)
        ->and($large->sku)->toBe('KML-L')
        ->and((int) $large->price)->toBe(160000);

    $images = $product->images()->get();

    expect($images)->toHaveCount(2)
        ->and($images->first()->is_primary)->toBeTrue()
        ->and($images->first()->path)->toMatch('#^assets/images/\d{10}_depan_[a-z0-9]{6}\.jpg$#')
        ->and($images->where('is_primary', true))->toHaveCount(1);

    $images->each(fn (ProductImage $image) => expect(File::exists(public_path($image->path)))->toBeTrue());
    expect(uploadedProductFiles())->toHaveCount(2);
});

it('gives a second product with the same name its own slug', function () {
    Product::factory()->create(['slug' => 'kemeja-linen-pendek']);

    $this->actingAs($this->seller)->post(route('seller.products.store'), productForm())->assertSessionHasNoErrors();

    expect(Product::where('store_id', $this->store->id)->sole()->slug)
        ->toStartWith('kemeja-linen-pendek-')
        ->not->toBe('kemeja-linen-pendek');
});

it('validates the form on the server and sends the errors back to the form', function (array $overrides, string $field) {
    $this->actingAs($this->seller)
        ->from(route('seller.products.create'))
        ->post(route('seller.products.store'), productForm($overrides))
        ->assertRedirect(route('seller.products.create'))
        ->assertSessionHasErrorsIn('product', $field);

    expect(Product::count())->toBe(0);
    expect(uploadedProductFiles())->toBe([]);
})->with([
    'nama kosong' => [['name' => ''], 'name'],
    'kategori tidak ada' => [['category_id' => 999], 'category_id'],
    'gender asing' => [['gender' => 'kids'], 'gender'],
    'harga terlalu murah' => [['base_price' => '500'], 'base_price'],
    'harga coret di bawah harga jual' => [['compare_at_price' => '100000'], 'compare_at_price'],
    'tanpa foto' => [['images' => []], 'images'],
    'file bukan gambar' => [['images' => [UploadedFile::fake()->create('katalog.pdf', 100, 'application/pdf')]], 'images.0'],
    'foto lebih dari 2 MB' => [['images' => [UploadedFile::fake()->image('besar.jpg', 900, 1125)->size(3000)]], 'images.0'],
    'foto terlalu kecil' => [['images' => [UploadedFile::fake()->image('kecil.jpg', 120, 120)]], 'images.0'],
    'terlalu banyak foto' => [['images' => array_map(fn (int $i) => UploadedFile::fake()->image("foto-{$i}.jpg", 900, 1125), range(1, 9))], 'images'],
    'tanpa varian' => [['variants' => []], 'variants'],
    'stok minus' => [['variants' => [['size_id' => 0, 'color_id' => '', 'stock' => '-1']]], 'variants.0.stock'],
]);

it('rejects a size from another kind of category and duplicate variant combinations', function () {
    $shoeSize = Size::factory()->footwear()->create();

    $this->actingAs($this->seller)
        ->post(route('seller.products.store'), productForm(['variants' => [
            ['size_id' => $shoeSize->id, 'color_id' => $this->color->id, 'stock' => '3'],
        ]]))
        ->assertSessionHasErrorsIn('product', 'variants.0.size_id');

    $this->actingAs($this->seller)
        ->post(route('seller.products.store'), productForm(['variants' => [
            ['size_id' => $this->sizeM->id, 'color_id' => $this->color->id, 'stock' => '3'],
            ['size_id' => $this->sizeM->id, 'color_id' => $this->color->id, 'stock' => '5'],
        ]]))
        ->assertSessionHasErrorsIn('product', 'variants');

    expect(Product::count())->toBe(0);
});

it('requires sizes only for categories that use them', function () {
    $accessories = Category::factory()->create(['category_type_id' => CategoryType::factory()->withoutSizes()->create()->id]);

    $this->actingAs($this->seller)
        ->post(route('seller.products.store'), productForm([
            'category_id' => $accessories->id,
            'variants' => [['size_id' => $this->sizeM->id, 'color_id' => '', 'stock' => '2']],
        ]))
        ->assertSessionHasErrorsIn('product', 'variants.0.size_id');

    $this->actingAs($this->seller)
        ->post(route('seller.products.store'), productForm([
            'category_id' => $accessories->id,
            'variants' => [['color_id' => '', 'stock' => '2']],
        ]))
        ->assertSessionHasNoErrors();

    expect(Product::sole()->variants()->sole()->size_id)->toBeNull();
});

it('shows server validation errors and the previous input on the form', function () {
    $this->actingAs($this->seller)
        ->followingRedirects()
        ->from(route('seller.products.create'))
        ->post(route('seller.products.store'), productForm(['name' => 'Kemeja Oke', 'base_price' => '500']))
        ->assertOk()
        ->assertSee('Produk belum tersimpan')
        ->assertSee('Harga jual minimal Rp1.000.')
        ->assertSee('value="Kemeja Oke"', false);
});

it('updates details, variants, and photos of the seller\'s own product', function () {
    $product = Product::factory()->create(['store_id' => $this->store->id, 'category_id' => $this->category->id, 'base_price' => 100000]);
    $keep = ProductVariant::factory()->create(['product_id' => $product->id, 'size_id' => $this->sizeM->id, 'color_id' => $this->color->id, 'stock' => 2]);
    $drop = ProductVariant::factory()->create(['product_id' => $product->id, 'size_id' => $this->sizeL->id, 'color_id' => $this->color->id]);
    File::ensureDirectoryExists(public_path('assets/images'));
    File::put(public_path('assets/images/1700000000_lama.jpg'), 'x');
    $oldPrimary = ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'assets/images/1700000000_lama.jpg', 'is_primary' => true, 'sort_order' => 1]);
    $second = ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'images/products/demo-2.webp', 'is_primary' => false, 'sort_order' => 2]);

    $this->actingAs($this->seller)
        ->put(route('seller.products.update', $product), productForm([
            'name' => 'Kemeja Linen Baru',
            'base_price' => '175000',
            'compare_at_price' => '200000',
            'is_active' => '0',
            'images' => [UploadedFile::fake()->image('baru.webp', 900, 1125)],
            'remove_images' => [$oldPrimary->id],
            'primary_image_id' => $second->id,
            'variants' => [
                ['id' => $keep->id, 'size_id' => $this->sizeM->id, 'color_id' => $this->color->id, 'sku' => $keep->sku, 'price' => '', 'stock' => '25'],
                ['id' => '', 'size_id' => $this->sizeL->id, 'color_id' => '', 'sku' => '', 'price' => '', 'stock' => '7'],
            ],
        ]))
        ->assertRedirect(route('seller.products.index'))
        ->assertSessionHasNoErrors();

    $product->refresh();

    expect($product->name)->toBe('Kemeja Linen Baru')
        ->and($product->slug)->not->toBe('kemeja-linen-baru')
        ->and((int) $product->base_price)->toBe(175000)
        ->and($product->isOnSale())->toBeTrue()
        ->and($product->is_active)->toBeFalse()
        ->and($keep->fresh()->stock)->toBe(25)
        ->and($drop->fresh())->toBeNull()
        ->and($product->variants()->count())->toBe(2)
        ->and(ProductImage::find($oldPrimary->id))->toBeNull()
        ->and($product->primaryImage->id)->toBe($second->id)
        ->and($product->images()->count())->toBe(2);

    expect(File::exists(public_path('assets/images/1700000000_lama.jpg')))->toBeFalse()
        ->and(File::exists($this->publicPath))->toBeTrue();
});

it('promotes the next photo when the main photo is removed', function () {
    $product = Product::factory()->create(['store_id' => $this->store->id, 'category_id' => $this->category->id]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'size_id' => $this->sizeM->id, 'color_id' => $this->color->id]);
    $primary = ProductImage::factory()->create(['product_id' => $product->id, 'is_primary' => true, 'sort_order' => 1]);
    $next = ProductImage::factory()->create(['product_id' => $product->id, 'is_primary' => false, 'sort_order' => 2]);

    $this->actingAs($this->seller)
        ->put(route('seller.products.update', $product), productForm([
            'images' => [],
            'remove_images' => [$primary->id],
            'variants' => [['id' => $variant->id, 'size_id' => $this->sizeM->id, 'color_id' => $this->color->id, 'sku' => $variant->sku, 'stock' => '3']],
        ]))
        ->assertSessionHasNoErrors();

    expect($next->fresh()->is_primary)->toBeTrue();
});

it('refuses to remove every photo of a product', function () {
    $product = Product::factory()->create(['store_id' => $this->store->id, 'category_id' => $this->category->id]);
    $image = ProductImage::factory()->create(['product_id' => $product->id]);

    $this->actingAs($this->seller)
        ->put(route('seller.products.update', $product), productForm(['images' => [], 'remove_images' => [$image->id]]))
        ->assertSessionHasErrorsIn('product', 'images');

    expect($image->fresh())->not->toBeNull();
});

it('prefills the edit form with the product and its variants', function () {
    $product = Product::factory()->create(['store_id' => $this->store->id, 'category_id' => $this->category->id, 'name' => 'Kemeja Biru Laut', 'base_price' => 99000]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'size_id' => $this->sizeM->id, 'color_id' => $this->color->id, 'sku' => 'BIRU-M']);

    $this->actingAs($this->seller)
        ->get(route('seller.products.edit', $product))
        ->assertOk()
        ->assertSee('value="Kemeja Biru Laut"', false)
        ->assertSee('value="99000"', false)
        ->assertSee('BIRU-M')
        ->assertSee('name="_method" value="PUT"', false);
});

it('keeps sellers away from other stores\' products', function () {
    $otherProduct = Product::factory()->create();

    $this->actingAs($this->seller)->get(route('seller.products.edit', $otherProduct))->assertNotFound();
    $this->actingAs($this->seller)->put(route('seller.products.update', $otherProduct), productForm())->assertNotFound();

    Livewire::actingAs($this->seller)
        ->test('pages::seller-products')
        ->call('delete', $otherProduct->id)
        ->assertNotFound();

    expect($otherProduct->fresh()->trashed())->toBeFalse();
});

it('does not let accounts without a store save products', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('seller.products.create'))->assertOk()->assertSee('Belum punya toko');
    $this->actingAs($admin)->post(route('seller.products.store'), productForm())->assertForbidden();

    expect(Product::count())->toBe(0);
});

it('sends guests to the login page instead of saving', function () {
    $this->post(route('seller.products.store'), productForm())->assertRedirect(route('login'));

    expect(Product::count())->toBe(0);
});

it('lets the seller hide, show, and delete products from the list', function () {
    $product = Product::factory()->create(['store_id' => $this->store->id, 'name' => 'Kaos Polos']);

    $component = Livewire::actingAs($this->seller)
        ->test('pages::seller-products')
        ->assertSee('Tambah Produk')
        ->assertSee(route('seller.products.edit', $product))
        ->call('toggleActive', $product->id)
        ->assertDispatched('notify');

    expect($product->fresh()->is_active)->toBeFalse();

    $component->call('toggleActive', $product->id);
    expect($product->fresh()->is_active)->toBeTrue();

    $component->call('delete', $product->id)->assertDontSee('Kaos Polos');
    expect($product->fresh()->trashed())->toBeTrue();
});
