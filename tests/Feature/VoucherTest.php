<?php

/**
 * Kode promo di checkout: potongan persentase (bisa dibatasi maksimal) atau
 * potongan rupiah, hanya dari subtotal barang. Diskonnya dibagi ke paket per
 * toko sehingga escrow dan refund tetap sama dengan yang dibayar pembeli.
 */

use App\Models\Cart;
use App\Models\EscrowHold;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\VoucherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Keranjang dari dua toko: 2 kaos @Rp150.000 dan 1 celana @Rp200.000.
 * Subtotal Rp500.000, ongkir JNE Reguler 2 × Rp20.000, total Rp540.000.
 *
 * @return array{buyer: User, kaos: ProductVariant, celana: ProductVariant}
 */
function voucherCart(?User $buyer = null): array
{
    $buyer ??= User::factory()->create();

    $kaos = ProductVariant::factory()->create(['stock' => 10, 'price' => 150000]);
    $celana = ProductVariant::factory()->create(['stock' => 10, 'price' => 200000]);

    $cart = Cart::forUser($buyer);
    $cart->items()->create(['product_variant_id' => $kaos->id, 'quantity' => 2]);
    $cart->items()->create(['product_variant_id' => $celana->id, 'quantity' => 1]);

    return ['buyer' => $buyer, 'kaos' => $kaos, 'celana' => $celana];
}

/**
 * @return array<string, string>
 */
function voucherAddress(): array
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

function orderWithVoucher(User $buyer, string $code): Order
{
    return Order::placeFromCart($buyer, voucherAddress(), 'jne-reg', 'qris', voucherCode: $code);
}

it('takes a percentage off the goods subtotal', function () {
    ['buyer' => $buyer] = voucherCart();
    Voucher::factory()->percentage(10)->create(['code' => 'HEMAT10']);

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->assertSee('Rp 540.000')
        ->set('voucherCode', ' hemat10 ')
        ->call('applyVoucher')
        ->assertHasNoErrors()
        ->assertSet('appliedVoucherCode', 'HEMAT10')
        ->assertSet('voucherCode', '')
        ->assertDispatched('notify')
        ->assertSee('Diskon (10%)')
        ->assertSee('Rp 50.000')
        ->assertSee('Rp 490.000');
});

it('caps a percentage discount at its maximum', function () {
    ['buyer' => $buyer] = voucherCart();
    Voucher::factory()->percentage(50, maxDiscount: 100000)->create(['code' => 'SETENGAH']);

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->set('voucherCode', 'SETENGAH')
        ->call('applyVoucher')
        ->assertSee('Diskon (50% (maks. Rp100.000))')
        ->assertSee('Rp 440.000');
});

it('takes a fixed rupiah amount off', function () {
    ['buyer' => $buyer] = voucherCart();
    Voucher::factory()->fixed(25000)->create(['code' => 'HEMAT25K']);

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->set('voucherCode', 'HEMAT25K')
        ->call('applyVoucher')
        ->assertSee('Diskon (Rp25.000)')
        ->assertSee('Rp 515.000');
});

it('never discounts more than the goods subtotal or the shipping', function () {
    $voucher = Voucher::factory()->fixed(1000000)->make();

    expect($voucher->discountFor(500000))->toBe(500000.0)
        ->and(Voucher::factory()->percentage(15)->make()->discountFor(99999))->toBe(14999.0); // dibulatkan ke bawah
});

it('rejects a code that cannot be used', function (?array $voucher, string $message) {
    ['buyer' => $buyer] = voucherCart();

    if ($voucher !== null) {
        Voucher::factory()->create(['code' => 'PROMO', ...$voucher]);
    }

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->set('voucherCode', 'PROMO')
        ->call('applyVoucher')
        ->assertHasErrors('voucherCode')
        ->assertSee($message)
        ->assertSet('appliedVoucherCode', null)
        ->assertSee('Rp 540.000');
})->with([
    'unknown' => [null, 'Kode promo tidak ditemukan.'],
    'inactive' => [['is_active' => false], 'Kode promo ini sudah tidak berlaku.'],
    'expired' => [['expires_at' => now()->subDay()], 'Kode promo ini sudah kedaluwarsa.'],
    'not started' => [['starts_at' => now()->addWeek()], 'Kode promo ini baru berlaku mulai'],
    'below minimum' => [['min_subtotal' => 600000], 'Minimal belanja Rp 600.000 untuk memakai kode ini.'],
]);

it('asks for a code before checking it', function () {
    ['buyer' => $buyer] = voucherCart();

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->call('applyVoucher')
        ->assertHasErrors('voucherCode')
        ->assertSee('Masukkan kode promo.');
});

it('removes an applied code', function () {
    ['buyer' => $buyer] = voucherCart();
    Voucher::factory()->percentage(10)->create(['code' => 'HEMAT10']);

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->set('voucherCode', 'HEMAT10')
        ->call('applyVoucher')
        ->assertSee('Rp 490.000')
        ->call('removeVoucher')
        ->assertSet('appliedVoucherCode', null)
        ->assertDontSee('Rp 490.000')
        ->assertSee('Rp 540.000');
});

it('places the order with the discount split across the store packages', function () {
    ['buyer' => $buyer, 'kaos' => $kaos, 'celana' => $celana] = voucherCart();
    $voucher = Voucher::factory()->percentage(10)->create(['code' => 'HEMAT10']);

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->set('address', voucherAddress())
        ->set('voucherCode', 'HEMAT10')
        ->call('applyVoucher')
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = Order::with('storeOrders', 'payments')->sole();

    expect($order)
        ->voucher_id->toBe($voucher->id)
        ->voucher_code->toBe('HEMAT10')
        ->and((float) $order->subtotal)->toBe(500000.0)
        ->and((float) $order->discount_total)->toBe(50000.0)
        ->and((float) $order->grand_total)->toBe(490000.0)
        ->and((float) $order->payments->sole()->amount)->toBe(490000.0)
        ->and((float) $order->storeOrders->sum('total'))->toBe(490000.0);

    // Kaos Rp300.000 dari Rp500.000 → 60% diskon; komisi dari total setelah diskon.
    $kaosPackage = $order->storeOrders->firstWhere('store_id', $kaos->product->store_id);
    $celanaPackage = $order->storeOrders->firstWhere('store_id', $celana->product->store_id);

    expect((float) $kaosPackage->discount)->toBe(30000.0)
        ->and((float) $kaosPackage->total)->toBe(290000.0)
        ->and((float) $kaosPackage->platform_fee_amount)->toBe(14500.0)
        ->and((float) $kaosPackage->seller_earning)->toBe(275500.0)
        ->and((float) $celanaPackage->discount)->toBe(20000.0)
        ->and((float) $celanaPackage->total)->toBe(200000.0);
});

it('gives the rounding remainder to the last package', function () {
    $split = Order::splitDiscount(100, collect([7 => 100000.0, 8 => 100000.0, 9 => 100000.0]));

    expect($split->all())->toBe([7 => 33.0, 8 => 33.0, 9 => 34.0]);
});

it('holds and refunds what the buyer actually paid for a package', function () {
    ['buyer' => $buyer, 'kaos' => $kaos] = voucherCart();
    Voucher::factory()->fixed(50000)->create(['code' => 'POTONG50']);

    $order = orderWithVoucher($buyer, 'POTONG50');
    $payment = $order->payments()->sole();
    $payment->settle(Payment::SIMULATOR.'-'.$payment->reference);

    $kaosPackage = $order->storeOrders()->where('store_id', $kaos->product->store_id)->sole();

    expect((float) EscrowHold::where('store_order_id', $kaosPackage->id)->value('gross_amount'))->toBe(290000.0);

    $kaosPackage->cancel('Stok fisik ternyata rusak.');

    expect((float) Refund::sole()->amount)->toBe(290000.0)
        ->and((float) $buyer->wallet()->sole()->balance_available)->toBe(290000.0);
});

it('stops a code once its quota is used up and gives it back when the order is cancelled', function () {
    Voucher::factory()->create(['code' => 'TERBATAS', 'usage_limit' => 1]);

    ['buyer' => $first] = voucherCart();
    $firstOrder = orderWithVoucher($first, 'TERBATAS');

    ['buyer' => $second] = voucherCart();

    Livewire::actingAs($second)
        ->test('pages::checkout')
        ->set('voucherCode', 'TERBATAS')
        ->call('applyVoucher')
        ->assertHasErrors('voucherCode')
        ->assertSee('Kuota kode promo ini sudah habis.');

    $firstOrder->cancelUnpaid();

    Livewire::actingAs($second)
        ->test('pages::checkout')
        ->set('voucherCode', 'TERBATAS')
        ->call('applyVoucher')
        ->assertHasNoErrors()
        ->assertSet('appliedVoucherCode', 'TERBATAS');
});

it('limits how many times one buyer may use a code', function () {
    Voucher::factory()->create(['code' => 'SEKALI', 'per_user_limit' => 1]);

    ['buyer' => $buyer] = voucherCart();
    orderWithVoucher($buyer, 'SEKALI');
    voucherCart($buyer);

    Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->set('voucherCode', 'SEKALI')
        ->call('applyVoucher')
        ->assertHasErrors('voucherCode')
        ->assertSee('Kamu sudah mencapai batas pemakaian kode promo ini.');

    ['buyer' => $otherBuyer] = voucherCart();

    Livewire::actingAs($otherBuyer)
        ->test('pages::checkout')
        ->set('voucherCode', 'SEKALI')
        ->call('applyVoucher')
        ->assertHasNoErrors();
});

it('checks the code again when the order is placed', function () {
    ['buyer' => $buyer] = voucherCart();
    $voucher = Voucher::factory()->create(['code' => 'HEMAT10']);

    $checkout = Livewire::actingAs($buyer)
        ->test('pages::checkout')
        ->set('address', voucherAddress())
        ->set('voucherCode', 'HEMAT10')
        ->call('applyVoucher')
        ->assertHasNoErrors();

    $voucher->update(['is_active' => false]);

    $checkout->call('placeOrder')
        ->assertSee('Kode promo ini sudah tidak berlaku.');

    expect(Order::count())->toBe(0)
        ->and($buyer->cartQuantity())->toBe(3)
        ->and(fn () => orderWithVoucher($buyer, 'HEMAT10'))->toThrow(ValidationException::class);
});

it('shows the code on the buyer receipt and the discount on the seller package', function () {
    ['buyer' => $buyer, 'kaos' => $kaos] = voucherCart();
    Voucher::factory()->percentage(10)->create(['code' => 'HEMAT10']);

    $order = orderWithVoucher($buyer, 'HEMAT10');
    $kaosPackage = $order->storeOrders()->where('store_id', $kaos->product->store_id)->sole();

    Livewire::actingAs($buyer)
        ->test('pages::user-order-detail', ['order' => $order])
        ->assertSee('Diskon (HEMAT10)')
        ->assertSee('Rp 50.000');

    Livewire::actingAs($kaosPackage->store->owner)
        ->test('pages::seller-order-detail', ['storeOrder' => $kaosPackage])
        ->assertSee('Diskon voucher')
        ->assertSee('Rp 30.000');
});

it('seeds the demo codes without duplicating them', function () {
    $this->seed(VoucherSeeder::class);
    $this->seed(VoucherSeeder::class);

    expect(Voucher::count())->toBe(4)
        ->and(Voucher::query()->withCode('fitmate10')->sole())
        ->type->toBe('percentage')
        ->is_active->toBeTrue();
});
