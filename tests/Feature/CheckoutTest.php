<?php

/**
 * Checkout: keranjang → satu order dengan satu paket per toko, data produk dan
 * alamat disalin, stok dikurangi, instruksi bayar dibuat.
 */

use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Pembeli dengan keranjang berisi barang dari dua toko: 2 kaos (Rp150.000,
 * stok 5) dan 1 celana (Rp200.000, stok 3).
 *
 * @return array{buyer: User, kaos: ProductVariant, celana: ProductVariant}
 */
function checkoutCart(?User $buyer = null): array
{
    $buyer ??= User::factory()->create();

    $kaos = ProductVariant::factory()->create(['stock' => 5]);
    $kaos->product->update(['name' => 'Kaos Uji Checkout', 'base_price' => 150000]);

    $celana = ProductVariant::factory()->create(['stock' => 3, 'price' => 200000]);
    $celana->product->update(['name' => 'Celana Uji Checkout']);

    $cart = Cart::forUser($buyer);
    $cart->items()->create(['product_variant_id' => $kaos->id, 'quantity' => 2]);
    $cart->items()->create(['product_variant_id' => $celana->id, 'quantity' => 1]);

    return ['buyer' => $buyer, 'kaos' => $kaos->fresh(), 'celana' => $celana->fresh()];
}

/**
 * @return array<string, string>
 */
function checkoutAddress(): array
{
    return [
        'recipient_name' => 'Zahir',
        'recipient_phone' => '081200000000',
        'street' => 'Jl. Merdeka No. 1',
        'city' => 'Bandung',
        'state' => 'Jawa Barat',
        'postal_code' => '40111',
    ];
}

it('keeps guests and staff out of checkout', function () {
    $this->get(route('checkout'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('checkout'))
        ->assertRedirect('/');
});

it('sends the buyer back to the cart when it is empty', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::checkout')
        ->assertRedirect(route('user.cart'));
});

it('shows the cart split per store with shipping charged per package', function () {
    ['buyer' => $buyer] = checkoutCart();
    UserAddress::factory()->for($buyer)->create(['is_default' => true, 'city' => 'Surabaya']);

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->assertSet('address.city', 'Surabaya')
        ->assertSee('Kaos Uji Checkout')
        ->assertSee('Celana Uji Checkout')
        ->assertSee('Ongkir (2 paket)')
        ->assertSee('Rp 500.000') // 2 × 150.000 + 200.000
        ->assertSee('Rp 540.000') // + 2 × 20.000 (JNE Reguler)
        ->set('shippingOption', 'sicepat-best')
        ->assertSee('Rp 570.000'); // + 2 × 35.000
});

it('turns the cart into one order with a package per store', function () {
    ['buyer' => $buyer, 'kaos' => $kaos, 'celana' => $celana] = checkoutCart();

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->set('address', checkoutAddress())
        ->set('paymentMethod', 'virtual_account')
        ->set('vaBank', 'bni')
        ->set('note', 'Titip di satpam')
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertRedirect(route('user.orders.show', Order::sole()));

    $order = Order::with('storeOrders.items', 'storeOrders.shipment', 'payments')->sole();

    expect($order)
        ->user_id->toBe($buyer->id)
        ->status->toBe('pending_payment')
        ->shipping_city->toBe('Bandung')
        ->shipping_province->toBe('Jawa Barat')
        ->note->toBe('Titip di satpam')
        ->and((float) $order->subtotal)->toBe(500000.0)
        ->and((float) $order->shipping_total)->toBe(40000.0)
        ->and((float) $order->grand_total)->toBe(540000.0)
        ->and($order->expires_at->isFuture())->toBeTrue()
        ->and($order->storeOrders)->toHaveCount(2);

    $kaosPackage = $order->storeOrders->firstWhere('store_id', $kaos->product->store_id);

    expect((float) $kaosPackage->total)->toBe(320000.0)
        ->and((float) $kaosPackage->platform_fee_amount)->toBe(16000.0)
        ->and((float) $kaosPackage->seller_earning)->toBe(304000.0)
        ->and($kaosPackage->shipment->courier)->toBe('jne')
        ->and($kaosPackage->items->sole())
        ->product_name->toBe('Kaos Uji Checkout')
        ->quantity->toBe(2)
        ->size_label->toBe($kaos->size->name)
        ->color_name->toBe($kaos->color->name);

    expect($order->payments->sole())
        ->method->toBe('virtual_account')
        ->va_bank->toBe('bni')
        ->status->toBe('pending')
        ->and((float) $order->payments->sole()->amount)->toBe(540000.0);

    expect($kaos->fresh()->stock)->toBe(3)
        ->and($celana->fresh()->stock)->toBe(2)
        ->and($buyer->cartQuantity())->toBe(0);
});

it('keeps the receipt unchanged when the seller edits the price later', function () {
    ['buyer' => $buyer, 'kaos' => $kaos] = checkoutCart();

    $order = Order::placeFromCart($buyer, checkoutAddress(), 'jne-reg', 'qris');

    $kaos->product->update(['name' => 'Nama Baru', 'base_price' => 999000]);

    $item = $order->items()->where('product_variant_id', $kaos->id)->sole();

    expect($item->product_name)->toBe('Kaos Uji Checkout')
        ->and((float) $item->unit_price)->toBe(150000.0);
});

it('refuses to sell more than what is left in stock', function () {
    ['buyer' => $buyer, 'kaos' => $kaos, 'celana' => $celana] = checkoutCart();

    // Pembeli lain lebih dulu memborong kaosnya.
    $kaos->update(['stock' => 1]);

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->assertSee('Stok Kaos Uji Checkout tinggal 1.')
        ->set('address', checkoutAddress())
        ->call('placeOrder')
        ->assertHasErrors('cart');

    expect(Order::count())->toBe(0)
        ->and($kaos->fresh()->stock)->toBe(1)
        ->and($celana->fresh()->stock)->toBe(3)
        ->and($buyer->cartQuantity())->toBe(3);
});

it('refuses products from a store that stopped selling', function () {
    ['buyer' => $buyer, 'celana' => $celana] = checkoutCart();
    $celana->product->store->update(['status' => 'suspended']);

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->set('address', checkoutAddress())
        ->call('placeOrder')
        ->assertHasErrors('cart');

    expect(Order::count())->toBe(0);
});

it('does not let a seller buy from their own store', function () {
    $store = Store::factory()->create();
    ['buyer' => $seller, 'kaos' => $kaos] = checkoutCart($store->owner);
    $kaos->product->update(['store_id' => $store->id]);

    Livewire::actingAs($seller)
        ->test('pages::checkout')
        ->set('address', checkoutAddress())
        ->call('placeOrder')
        ->assertHasErrors('cart');

    expect(Order::count())->toBe(0);
});

it('requires the shipping address', function () {
    ['buyer' => $buyer] = checkoutCart();

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->set('address.street', '')
        ->set('address.city', '')
        ->call('placeOrder')
        ->assertHasErrors(['address.street' => 'required', 'address.city' => 'required']);

    expect(Order::count())->toBe(0);
});

it('saves the address as the default one for a buyer who has none', function () {
    ['buyer' => $buyer] = checkoutCart();

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->set('address', checkoutAddress())
        ->call('placeOrder')
        ->assertHasNoErrors();

    expect($buyer->defaultAddress()->sole())
        ->street->toBe('Jl. Merdeka No. 1')
        ->country->toBe('Indonesia');
});
