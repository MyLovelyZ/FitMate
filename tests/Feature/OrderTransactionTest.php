<?php

/**
 * Perjalanan uang setelah checkout: bayar → escrow per toko → dikemas → dikirim
 * → diterima → dana cair ke seller. Plus jalur gagalnya: dibatalkan pembeli,
 * kedaluwarsa, dibatalkan seller (refund), dan pencairan otomatis.
 */

use App\Models\Cart;
use App\Models\Dispute;
use App\Models\EscrowHold;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Order belum dibayar dengan dua paket: 2 kaos @Rp150.000 (stok awal 5) dan
 * 1 celana @Rp200.000 (stok awal 3), dikirim JNE Reguler (Rp20.000 per paket).
 *
 * Paket kaos: total 320.000, komisi 16.000, hak seller 304.000.
 * Paket celana: total 220.000, komisi 11.000, hak seller 209.000.
 *
 * @return array{order: Order, buyer: User, kaos: ProductVariant, celana: ProductVariant}
 */
function transactionOrder(): array
{
    $buyer = User::factory()->create();
    $kaos = ProductVariant::factory()->create(['stock' => 5, 'price' => 150000]);
    $celana = ProductVariant::factory()->create(['stock' => 3, 'price' => 200000]);

    $cart = Cart::forUser($buyer);
    $cart->items()->create(['product_variant_id' => $kaos->id, 'quantity' => 2]);
    $cart->items()->create(['product_variant_id' => $celana->id, 'quantity' => 1]);

    $order = Order::placeFromCart($buyer, [
        'recipient_name' => 'Zahir',
        'recipient_phone' => '081200000000',
        'street' => 'Jl. Merdeka No. 1',
        'city' => 'Bandung',
        'state' => 'Jawa Barat',
        'postal_code' => '40111',
    ], 'jne-reg', 'virtual_account', 'bca');

    return ['order' => $order, 'buyer' => $buyer, 'kaos' => $kaos, 'celana' => $celana];
}

function payTransactionOrder(Order $order): void
{
    $payment = $order->payments()->sole();
    $payment->settle(Payment::SIMULATOR.'-'.$payment->reference);
}

function packageFor(Order $order, ProductVariant $variant): StoreOrder
{
    return $order->storeOrders()->where('store_id', $variant->product->store_id)->sole();
}

it('pays from the order page and holds each store\'s share in escrow', function () {
    ['order' => $order, 'buyer' => $buyer, 'kaos' => $kaos] = transactionOrder();

    Livewire::actingAs($buyer)
        ->test('pages::user-order-detail', ['order' => $order])
        ->assertSee('Bayar Sekarang (Simulasi)')
        ->call('pay')
        ->assertRedirect(route('checkout.success', $order));

    $order->refresh();

    expect($order->status)->toBe('paid')
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->payments()->sole()->status)->toBe('paid')
        ->and(EscrowHold::where('status', 'held')->count())->toBe(2);

    $sellerWallet = $kaos->product->store->owner->wallet;

    expect((float) $sellerWallet->balance_pending)->toBe(304000.0)
        ->and((float) $sellerWallet->balance_available)->toBe(0.0);

    $this->actingAs($buyer)
        ->get(route('checkout.success', $order))
        ->assertOk()
        ->assertSee('Pembayaran Berhasil')
        ->assertSee($order->order_number)
        ->assertSee('Rp 540.000');
});

it('processes the same payment notification only once', function () {
    ['order' => $order, 'celana' => $celana] = transactionOrder();
    $payment = $order->payments()->sole();

    expect($payment->settle('evt-1'))->toBeTrue()
        ->and($payment->settle('evt-1'))->toBeFalse()
        ->and($payment->settle('evt-2'))->toBeFalse()
        ->and(PaymentEvent::count())->toBe(2)
        ->and(EscrowHold::count())->toBe(2)
        ->and((float) $celana->product->store->owner->wallet->balance_pending)->toBe(209000.0);
});

it('shows the success page only for the buyer\'s own paid order', function () {
    ['order' => $order, 'buyer' => $buyer] = transactionOrder();

    $this->actingAs($buyer)
        ->get(route('checkout.success', $order))
        ->assertRedirect(route('user.orders.show', $order));

    payTransactionOrder($order);

    $this->actingAs(User::factory()->create())
        ->get(route('checkout.success', $order))
        ->assertNotFound();
});

it('lets the buyer cancel an unpaid order and puts the stock back', function () {
    ['order' => $order, 'buyer' => $buyer, 'kaos' => $kaos, 'celana' => $celana] = transactionOrder();

    Livewire::actingAs($buyer)
        ->test('pages::user-order-detail', ['order' => $order])
        ->call('cancelOrder')
        ->assertDispatched('notify');

    expect($order->fresh()->status)->toBe('cancelled')
        ->and($order->payments()->sole()->status)->toBe('failed')
        ->and($order->storeOrders()->pluck('status')->unique()->all())->toBe(['cancelled'])
        ->and($kaos->fresh()->stock)->toBe(5)
        ->and($celana->fresh()->stock)->toBe(3);
});

it('expires unpaid orders after the payment window and puts the stock back', function () {
    ['order' => $unpaid, 'kaos' => $kaos] = transactionOrder();
    ['order' => $paid] = transactionOrder();
    payTransactionOrder($paid);

    $this->travel(Order::PAYMENT_WINDOW_HOURS + 1)->hours();

    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($unpaid->fresh()->status)->toBe('expired')
        ->and($unpaid->payments()->sole()->status)->toBe('expired')
        ->and($kaos->fresh()->stock)->toBe(5)
        ->and($paid->fresh()->status)->toBe('paid');
});

it('refuses a payment that arrives after the deadline', function () {
    ['order' => $order, 'buyer' => $buyer] = transactionOrder();

    $this->travel(Order::PAYMENT_WINDOW_HOURS + 1)->hours();

    Livewire::actingAs($buyer)
        ->test('pages::user-order-detail', ['order' => $order])
        ->call('pay')
        ->assertNoRedirect();

    expect($order->fresh()->status)->toBe('expired')
        ->and($order->fresh()->paid_at)->toBeNull()
        ->and(EscrowHold::count())->toBe(0);
});

it('walks a package from packing to delivery and pays the seller once the buyer confirms', function () {
    ['order' => $order, 'buyer' => $buyer, 'kaos' => $kaos, 'celana' => $celana] = transactionOrder();
    payTransactionOrder($order);

    $package = packageFor($order, $kaos);
    $seller = $package->store->owner;

    Livewire::actingAs($seller)
        ->test('pages::seller-order-detail', ['storeOrder' => $package])
        ->call('process')
        ->call('ship')
        ->assertHasErrors(['trackingNumber' => 'required'])
        ->set('trackingNumber', 'JNE123456789')
        ->call('ship')
        ->assertHasNoErrors()
        ->call('markDelivered');

    $package->refresh();

    expect($package->status)->toBe('delivered')
        ->and($package->shipment->tracking_number)->toBe('JNE123456789')
        ->and($package->shipment->status)->toBe('delivered')
        ->and($package->escrowHold->auto_release_at->isFuture())->toBeTrue()
        ->and($order->fresh()->status)->toBe('processing');

    Livewire::actingAs($buyer)
        ->test('pages::user-order-detail', ['order' => $order->fresh()])
        ->call('confirmReceived', $package->id)
        ->assertDispatched('notify');

    $package->refresh();
    $wallet = $seller->wallet->fresh();

    expect($package->status)->toBe('completed')
        ->and($package->buyer_confirmed_at)->not->toBeNull()
        ->and($package->escrowHold->status)->toBe('released')
        ->and($package->escrowHold->release_reason)->toBe('buyer_confirmed')
        ->and((float) $wallet->balance_available)->toBe(304000.0)
        ->and((float) $wallet->balance_pending)->toBe(0.0)
        ->and($wallet->isBalanced())->toBeTrue();

    $other = packageFor($order, $celana);
    $other->process();
    $other->ship('JNT987654321');
    $other->complete('buyer_confirmed');

    expect($order->fresh()->status)->toBe('completed');
});

it('rejects package steps taken out of order', function () {
    ['order' => $order, 'buyer' => $buyer, 'kaos' => $kaos] = transactionOrder();
    $package = packageFor($order, $kaos);

    expect(fn () => $package->process())->toThrow(LogicException::class);

    Livewire::actingAs($package->store->owner)
        ->test('pages::seller-order-detail', ['storeOrder' => $package])
        ->call('process')
        ->assertForbidden();

    payTransactionOrder($order);

    expect(fn () => $package->fresh()->ship('JNE1'))->toThrow(LogicException::class)
        ->and(fn () => $package->fresh()->complete('buyer_confirmed'))->toThrow(LogicException::class);

    Livewire::actingAs($buyer)
        ->test('pages::user-order-detail', ['order' => $order->fresh()])
        ->call('confirmReceived', $package->id)
        ->assertForbidden();
});

it('keeps a seller out of other stores\' packages', function () {
    ['order' => $order, 'kaos' => $kaos] = transactionOrder();
    $package = packageFor($order, $kaos);
    $otherSeller = Store::factory()->create()->owner;

    $this->actingAs($package->store->owner)
        ->get(route('seller.orders.show', $package))
        ->assertOk()
        ->assertSee('Menunggu Pembayaran');

    $this->actingAs($otherSeller)
        ->get(route('seller.orders.show', $package))
        ->assertNotFound();
});

it('refunds the buyer when the seller cancels a paid package', function () {
    ['order' => $order, 'buyer' => $buyer, 'kaos' => $kaos, 'celana' => $celana] = transactionOrder();
    payTransactionOrder($order);

    $package = packageFor($order, $kaos);
    $seller = $package->store->owner;

    Livewire::actingAs($seller)
        ->test('pages::seller-order-detail', ['storeOrder' => $package])
        ->call('cancel')
        ->assertHasErrors(['cancelReason' => 'required'])
        ->set('cancelReason', 'Stok rusak saat dicek')
        ->call('cancel')
        ->assertHasNoErrors();

    $package->refresh();

    expect($package->status)->toBe('cancelled')
        ->and($package->escrowHold->status)->toBe('refunded')
        ->and($kaos->fresh()->stock)->toBe(5)
        ->and(Refund::sole())
        ->destination->toBe('wallet')
        ->requested_by->toBe($seller->id)
        ->and((float) $buyer->wallet->balance_available)->toBe(320000.0)
        ->and($buyer->wallet->isBalanced())->toBeTrue()
        ->and((float) $seller->wallet->fresh()->balance_pending)->toBe(0.0)
        ->and($order->fresh()->status)->toBe('paid');

    packageFor($order, $celana)->cancel('Barang habis.', $celana->product->store->owner);

    expect($order->fresh()->status)->toBe('refunded');
});

it('releases delivered packages automatically after the waiting period unless disputed', function () {
    ['order' => $order, 'kaos' => $kaos, 'celana' => $celana] = transactionOrder();
    payTransactionOrder($order);

    foreach ([$kaos, $celana] as $variant) {
        $package = packageFor($order, $variant);
        $package->process();
        $package->ship('RESI-'.$variant->id);
        $package->markDelivered();
    }

    Dispute::factory()->for(packageFor($order, $celana))->create(['opened_by' => $order->user_id, 'status' => 'open']);

    $this->travel(EscrowHold::AUTO_RELEASE_DAYS + 1)->days();

    $this->artisan('escrow:release-due')->assertSuccessful();

    expect(packageFor($order, $kaos))
        ->status->toBe('completed')
        ->escrowHold->release_reason->toBe('auto_released')
        ->and(packageFor($order, $celana)->escrowHold->status)->toBe('held');
});
