<?php

/**
 * Back-office transaksi (AdminController): kartu statistik count()/sum(), urutan
 * latest(), tombol ubah status lewat POST /admin/transactions/{id}/status, dan
 * nota pembeli /berhasil/{order} yang ikut berubah.
 */

use App\Models\Cart;
use App\Models\EscrowHold;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * Pesanan baru (belum dibayar) berisi 2 barang @Rp100.000 + ongkir JNE Reguler.
 */
function adminTestOrder(?User $buyer = null, int $stock = 5): Order
{
    $buyer ??= User::factory()->create();
    $variant = ProductVariant::factory()->create(['stock' => $stock, 'price' => 100000]);

    Cart::forUser($buyer)->items()->create(['product_variant_id' => $variant->id, 'quantity' => 2]);

    return Order::placeFromCart($buyer, [
        'recipient_name' => 'Zahir',
        'recipient_phone' => '081200000000',
        'street' => 'Jl. Merdeka No. 1',
        'city' => 'Bandung',
        'state' => 'Jawa Barat',
        'postal_code' => '40111',
    ], 'jne-reg', 'qris');
}

function setStatus(Order $order, string $status, array $extra = []): TestResponse
{
    return test()->actingAs(test()->admin)
        ->from(route('admin.transactions.index'))
        ->post(route('admin.transactions.status', $order), ['status' => $status, ...$extra]);
}

it('uses the separate admin master layout', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.transactions.index'))
        ->assertOk()
        ->assertSee('Back-Office')
        ->assertDontSee('ALL RIGHTS RESERVED');

    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Back-Office');
});

it('shows statistics that match the database', function () {
    $paid = adminTestOrder();
    setStatus($paid, 'paid');
    adminTestOrder();
    Product::factory()->count(2)->create();

    $productCount = Product::count();
    $revenue = (int) Order::whereIn('status', ['paid', 'processing', 'shipped', 'completed'])->sum('grand_total');

    expect($revenue)->toBe((int) $paid->fresh()->grand_total);

    $this->actingAs($this->admin)
        ->get(route('admin.transactions.index'))
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats): bool => $stats['Total Produk'] === (string) $productCount
            && $stats['Total Pesanan Masuk'] === '2'
            && $stats['Menunggu Pembayaran'] === '1'
            && $stats['Total Omzet Penjualan'] === 'Rp '.number_format($revenue, 0, ',', '.'));
});

it('lists the newest order on the top row', function () {
    $older = adminTestOrder();
    $older->forceFill(['created_at' => now()->subHour()])->save();
    $newest = adminTestOrder();

    $this->actingAs($this->admin)
        ->get(route('admin.transactions.index'))
        ->assertOk()
        ->assertSeeInOrder([$newest->order_number, $older->order_number]);
});

it('filters by status and searches by order number', function () {
    $unpaid = adminTestOrder();
    $paid = adminTestOrder();
    setStatus($paid, 'paid');

    $this->actingAs($this->admin)
        ->get(route('admin.transactions.index', ['status' => 'paid']))
        ->assertSee($paid->order_number)
        ->assertDontSee($unpaid->order_number);

    $this->actingAs($this->admin)
        ->get(route('admin.transactions.index', ['q' => $unpaid->order_number]))
        ->assertSee($unpaid->order_number)
        ->assertDontSee($paid->order_number);
});

it('marks an unpaid order as paid and holds the money in escrow', function () {
    $order = adminTestOrder();

    setStatus($order, 'paid')
        ->assertRedirect(route('admin.transactions.index'))
        ->assertSessionHas('success', "Status pesanan {$order->order_number} sekarang: Lunas.");

    $order->refresh();

    expect($order->status)->toBe('paid')
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->payments()->sole()->status)->toBe('paid')
        ->and(EscrowHold::where('status', 'held')->count())->toBe(1);

    $this->actingAs($this->admin)
        ->followingRedirects()
        ->from(route('admin.transactions.index'))
        ->post(route('admin.transactions.status', $order), ['status' => 'paid'])
        ->assertSee('tidak bisa diubah');
});

it('requires a tracking number to mark an order shipped', function () {
    $order = adminTestOrder();
    setStatus($order, 'paid');

    setStatus($order, 'shipped')->assertSessionHasErrors('tracking_number');

    expect($order->fresh()->status)->toBe('paid');
});

it('ships every pending package with the tracking number, then completes it', function () {
    $order = adminTestOrder();
    setStatus($order, 'paid');

    setStatus($order, 'shipped', ['tracking_number' => ' JNE123456 '])->assertSessionHas('success');

    $package = $order->storeOrders()->sole();

    expect($order->fresh()->status)->toBe('shipped')
        ->and($package->status)->toBe('shipped')
        ->and($package->shipment->tracking_number)->toBe('JNE123456');

    setStatus($order, 'completed')->assertSessionHas('success');

    expect($order->fresh()->status)->toBe('completed')
        ->and($package->fresh()->escrowHold->release_reason)->toBe('admin_released');
});

it('refuses a status change that skips a step', function () {
    $order = adminTestOrder();

    setStatus($order, 'shipped', ['tracking_number' => 'RESI1'])
        ->assertSessionHas('failure');

    setStatus($order, 'unknown')->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe('pending_payment');
});

it('cancels orders with a reason and puts the stock back', function () {
    $unpaid = adminTestOrder();
    $variant = ProductVariant::sole();

    setStatus($unpaid, 'cancelled')->assertSessionHasErrors('reason');
    setStatus($unpaid, 'cancelled', ['reason' => 'Pembeli minta batal lewat CS'])->assertSessionHas('success');

    expect($unpaid->fresh()->status)->toBe('cancelled')
        ->and($unpaid->storeOrders()->sole()->note)->toBe('Dibatalkan admin: Pembeli minta batal lewat CS')
        ->and($variant->fresh()->stock)->toBe(5);

    $paid = adminTestOrder();
    setStatus($paid, 'paid');
    setStatus($paid, 'cancelled', ['reason' => 'Stok rusak'])->assertSessionHas('success');

    expect($paid->fresh()->status)->toBe('refunded')
        ->and($paid->storeOrders()->sole()->escrowHold->status)->toBe('refunded');
});

it('keeps non-admins out of the transactions back-office', function () {
    $order = adminTestOrder();
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)->get(route('admin.transactions.index'))->assertRedirect('/');
    $this->actingAs($seller)->post(route('admin.transactions.status', $order), ['status' => 'paid'])->assertRedirect('/');

    expect($order->fresh()->status)->toBe('pending_payment');
});

it('updates the buyer invoice status and colour when the admin changes the order', function () {
    $buyer = User::factory()->create();
    $order = adminTestOrder($buyer);

    $this->actingAs($buyer)
        ->get(route('orders.invoice', $order))
        ->assertOk()
        ->assertSee('Belum Dibayar')
        ->assertSee('bg-sale/10');

    setStatus($order, 'paid');
    setStatus($order, 'shipped', ['tracking_number' => 'JNE777']);

    $this->actingAs($buyer)
        ->get(route('orders.invoice', $order))
        ->assertOk()
        ->assertSee('Dalam Perjalanan')
        ->assertSee('bg-ink text-cream', false)
        ->assertSee('JNE777')
        ->assertSee('wire:poll', false);
});

it('shows the invoice only to the buyer who placed the order', function () {
    $order = adminTestOrder();

    $this->actingAs(User::factory()->create())->get(route('orders.invoice', $order))->assertNotFound();
});

it('sends guests from the invoice to the login page', function () {
    $this->get(route('orders.invoice', adminTestOrder()))->assertRedirect(route('login'));
});
