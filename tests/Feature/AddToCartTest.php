<?php

/**
 * Tombol "Add to Bag" di halaman produk dan pengaturan jumlah di halaman keranjang.
 */

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Produk satu ukuran (S) dengan dua warna, masing-masing punya stok sendiri.
 *
 * @return array{product: Product, black: ProductVariant, cream: ProductVariant}
 */
function cartProduct(int $blackStock = 5, int $creamStock = 5): array
{
    $product = Product::factory()->create(['name' => 'Kaos Uji Keranjang', 'base_price' => 150000]);
    $size = Size::factory()->create([
        'category_type_id' => $product->category->category_type_id,
        'name' => 'S',
        'code' => 'S',
        'sort_order' => 1,
    ]);

    $variant = fn (string $colorName, int $stock): ProductVariant => ProductVariant::factory()->create([
        'product_id' => $product->id,
        'size_id' => $size->id,
        'color_id' => Color::factory()->create(['name' => $colorName])->id,
        'stock' => $stock,
    ]);

    return [
        'product' => $product,
        'black' => $variant('Hitam', $blackStock),
        'cream' => $variant('Krem', $creamStock),
    ];
}

it('sends guests to login and brings them back to the product afterwards', function () {
    ['product' => $product] = cartProduct();

    Livewire::test('pages::detail-product', ['product' => $product])
        ->call('addToCart', 1)
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('products.show', $product))
        ->and(CartItem::count())->toBe(0);
});

it('adds the selected variant to the buyer cart', function () {
    $buyer = User::factory()->create();
    ['product' => $product, 'black' => $black] = cartProduct();

    Livewire::actingAs($buyer)
        ->test('pages::detail-product', ['product' => $product])
        ->call('selectColor', $black->color_id)
        ->call('addToCart', 2)
        ->assertHasNoErrors()
        ->assertDispatched('cart-updated', count: 2)
        ->assertDispatched('notify');

    $item = CartItem::sole();

    expect($item->product_variant_id)->toBe($black->id)
        ->and($item->quantity)->toBe(2)
        ->and($item->cart->user_id)->toBe($buyer->id);
});

it('adds the color the buyer picked, not just the first one in stock', function () {
    $buyer = User::factory()->create();
    ['product' => $product, 'cream' => $cream] = cartProduct();

    Livewire::actingAs($buyer)
        ->test('pages::detail-product', ['product' => $product])
        ->call('selectColor', $cream->color_id)
        ->assertSet('selectedColorId', $cream->color_id)
        ->call('addToCart', 1);

    expect(CartItem::sole()->product_variant_id)->toBe($cream->id);
});

it('increases the quantity when the same variant is added again', function () {
    $buyer = User::factory()->create();
    ['product' => $product, 'black' => $black] = cartProduct();

    $component = Livewire::actingAs($buyer)
        ->test('pages::detail-product', ['product' => $product])
        ->call('selectColor', $black->color_id);

    $component->call('addToCart', 1);
    $component->call('addToCart', 2)->assertDispatched('cart-updated', count: 3);

    expect(CartItem::sole()->quantity)->toBe(3);
});

it('never puts more in the cart than the variant has in stock', function () {
    $buyer = User::factory()->create();
    ['product' => $product, 'black' => $black] = cartProduct(blackStock: 3);

    $component = Livewire::actingAs($buyer)
        ->test('pages::detail-product', ['product' => $product])
        ->call('selectColor', $black->color_id)
        ->call('addToCart', 10)
        ->assertHasNoErrors();

    expect(CartItem::sole()->quantity)->toBe(3);

    $component->call('addToCart', 1)->assertHasErrors('cart');

    expect(CartItem::sole()->quantity)->toBe(3);
});

it('preselects a color in stock and shows sold out for an empty one', function () {
    ['product' => $product, 'black' => $black, 'cream' => $cream] = cartProduct(blackStock: 0, creamStock: 4);

    Livewire::test('pages::detail-product', ['product' => $product])
        ->assertSet('selectedColorId', $cream->color_id)
        ->call('selectColor', $black->color_id)
        ->assertSee('Sold Out');
});

it('keeps staff accounts out of the cart', function () {
    ['product' => $product] = cartProduct();
    $staff = User::factory()->customerService()->create();

    $this->actingAs($staff)
        ->get(route('products.show', $product))
        ->assertSee('Khusus Akun Pembeli');

    Livewire::actingAs($staff)
        ->test('pages::detail-product', ['product' => $product])
        ->call('addToCart', 1)
        ->assertHasErrors('cart');

    expect(CartItem::count())->toBe(0);
});

it('shows the cart count badge in the navbar', function () {
    $buyer = User::factory()->create();
    ['black' => $black] = cartProduct();
    Cart::forUser($buyer)->add($black, 2);

    $this->actingAs($buyer)
        ->get(route('home'))
        ->assertSee('x-data="{ count: 2 }"', false);
});

it('changes the quantity on the cart page within the stock limit', function () {
    $buyer = User::factory()->create();
    ['black' => $black] = cartProduct(blackStock: 2);
    Cart::forUser($buyer)->add($black, 1);
    $item = CartItem::sole();

    $component = Livewire::actingAs($buyer)->test('pages::user-cart');

    $component->call('changeQuantity', $item->id, 1)->assertDispatched('cart-updated', count: 2);
    $component->call('changeQuantity', $item->id, 1);

    expect($item->fresh()->quantity)->toBe(2);

    $component->call('changeQuantity', $item->id, -1);
    $component->call('changeQuantity', $item->id, -1)->assertDispatched('cart-updated', count: 0);

    expect(CartItem::find($item->id))->toBeNull();
});

it('ignores quantity changes on someone else\'s cart item', function () {
    ['black' => $black] = cartProduct();
    Cart::forUser(User::factory()->create())->add($black, 1);
    $item = CartItem::sole();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::user-cart')
        ->call('changeQuantity', $item->id, 1);

    expect($item->fresh()->quantity)->toBe(1);
});
