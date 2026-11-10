<?php

/**
 * Upload produk oleh admin (AdminController@storeProduct, view admin/create_product):
 * form multipart, foto dipindah ke public/assets/images/ berawalan time(),
 * validasi tipe/ukuran file di server, dan produknya langsung tampil di beranda
 * sampai bisa di-checkout.
 */

use App\Models\Cart;
use App\Models\Category;
use App\Models\CategoryType;
use App\Models\Color;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->publicPath = fakePublicPath();
    $this->withoutVite();

    $this->admin = User::factory()->admin()->create();
    $this->store = Store::factory()->create(['name' => 'Toko Linen']);
    $this->category = Category::factory()->create(['category_type_id' => CategoryType::factory()->withoutSizes()->create()->id]);
    $this->color = Color::factory()->create();
});

afterEach(function () {
    File::deleteDirectory($this->publicPath);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function adminProductForm(array $overrides = []): array
{
    return array_replace([
        'store_id' => test()->store->id,
        'name' => 'Topi Bucket Kanvas',
        'category_id' => test()->category->id,
        'gender' => 'unisex',
        'description' => 'Kanvas tebal.',
        'base_price' => '85000',
        'is_active' => '1',
        'images' => [UploadedFile::fake()->image('topi.jpg', 900, 1125)],
        'variants' => [['color_id' => test()->color->id, 'stock' => '12']],
    ], $overrides);
}

it('renders create_product with a multipart form and live photo preview', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.products.create'))
        ->assertOk()
        ->assertViewIs('admin.create_product')
        ->assertSee('method="POST"', false)
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('URL.createObjectURL', false)
        ->assertSee('Toko Linen');
});

it('moves the uploaded photo into public/assets/images with a time() prefix', function () {
    $before = time();

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductForm())
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHasNoErrors();

    $product = Product::sole();
    $path = $product->primaryImage->path;
    preg_match('#^assets/images/(\d+)_topi_[a-z0-9]{6}\.jpg$#', $path, $match);

    expect($product->store_id)->toBe($this->store->id)
        ->and($match)->not->toBeEmpty()
        ->and((int) $match[1])->toBeGreaterThanOrEqual($before)->toBeLessThanOrEqual(time())
        ->and(File::exists(public_path($path)))->toBeTrue();

    Livewire::actingAs($this->admin)
        ->test('pages::admin-products')
        ->assertSee('Topi Bucket Kanvas')
        ->assertSee(asset($path));
});

it('rejects files that are not images or are larger than 2 MB', function (UploadedFile $file) {
    $this->actingAs($this->admin)
        ->from(route('admin.products.create'))
        ->post(route('admin.products.store'), adminProductForm(['images' => [$file]]))
        ->assertRedirect(route('admin.products.create'))
        ->assertSessionHasErrors('images.0');

    expect(Product::count())->toBe(0)
        ->and(File::glob(public_path('assets/images/*')))->toBe([]);
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('katalog.pdf', 200, 'application/pdf'),
    'docx' => fn () => UploadedFile::fake()->create('deskripsi.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    'lebih dari 2 MB' => fn () => UploadedFile::fake()->image('besar.jpg', 900, 1125)->size(2500),
]);

it('shows the warning message from the server on the form', function () {
    $this->actingAs($this->admin)
        ->followingRedirects()
        ->from(route('admin.products.create'))
        ->post(route('admin.products.store'), adminProductForm(['images' => [UploadedFile::fake()->create('katalog.pdf', 200, 'application/pdf')]]))
        ->assertOk()
        ->assertSee('Produk belum tersimpan')
        ->assertSee('harus berformat JPG, PNG, atau WebP');
});

it('requires the store that owns the product', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductForm(['store_id' => '']))
        ->assertSessionHasErrors('store_id');
});

it('keeps sellers and buyers away from the admin product form', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)->get(route('admin.products.create'))->assertRedirect('/');
    $this->actingAs($seller)->post(route('admin.products.store'), adminProductForm())->assertRedirect('/');

    expect(Product::count())->toBe(0);
});

it('puts the new product on the home page and lets buyers check it out', function () {
    $this->actingAs($this->admin)->post(route('admin.products.store'), adminProductForm())->assertSessionHasNoErrors();
    $product = Product::sole();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Produk Terbaru')
        ->assertSee('Topi Bucket Kanvas')
        ->assertSee(route('products.show', $product));

    $buyer = User::factory()->create();
    expect(Cart::forUser($buyer)->add($product->variants()->sole(), 2))->toBe(2);

    $order = Order::placeFromCart($buyer, [
        'recipient_name' => 'Zahir',
        'recipient_phone' => '081200000000',
        'street' => 'Jl. Merdeka No. 1',
        'city' => 'Bandung',
        'state' => 'Jawa Barat',
        'postal_code' => '40111',
    ], 'jne-reg', 'qris');

    expect($order->items()->sole()->product_name)->toBe('Topi Bucket Kanvas')
        ->and($product->variants()->sole()->stock)->toBe(10);
});
