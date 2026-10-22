<?php

/**
 * Halaman pesanan pembeli: daftar per status (belum dibayar, diproses, dalam
 * perjalanan, selesai, dibatalkan) dan detail pesanan beserta pengirimannya.
 */

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Satu pesanan milik `$buyer` dengan satu paket toko dan satu barang.
 *
 * @param  array<string, mixed>  $attributes
 */
function buyerOrder(User $buyer, string $status, array $attributes = []): Order
{
    $order = Order::factory()->for($buyer)->create(['status' => $status, ...$attributes]);
    $storeOrder = StoreOrder::factory()->for($order)->create();
    OrderItem::factory()->for($storeOrder)->create(['product_name' => 'Barang '.$order->order_number]);

    return $order;
}

it('maps every order status to a buyer-facing group', function (string $status, string $group, ?int $step) {
    $order = Order::factory()->make(['status' => $status]);

    expect($order->buyerStatusGroup())->toBe($group)
        ->and($order->buyerStepIndex())->toBe($step);
})->with([
    ['pending_payment', 'belum-dibayar', 0],
    ['paid', 'diproses', 1],
    ['processing', 'diproses', 1],
    ['shipped', 'dikirim', 2],
    ['completed', 'selesai', 3],
    ['cancelled', 'dibatalkan', null],
    ['expired', 'dibatalkan', null],
    ['refunded', 'dibatalkan', null],
]);

it('lists only the buyer\'s own orders with a count per status tab', function () {
    $buyer = User::factory()->create();
    $unpaid = buyerOrder($buyer, 'pending_payment');
    $shipped = buyerOrder($buyer, 'shipped');
    buyerOrder($buyer, 'processing');
    $someoneElse = buyerOrder(User::factory()->create(), 'shipped');

    $component = Livewire::actingAs($buyer)->test('pages::user-orders');

    expect($component->get('counts'))->toMatchArray([
        '' => 3,
        'belum-dibayar' => 1,
        'diproses' => 1,
        'dikirim' => 1,
        'selesai' => 0,
        'dibatalkan' => 0,
    ]);

    $component
        ->assertSee($unpaid->order_number)
        ->assertSee($shipped->order_number)
        ->assertDontSee($someoneElse->order_number);
});

it('filters the list by status tab', function () {
    $buyer = User::factory()->create();
    $unpaid = buyerOrder($buyer, 'pending_payment');
    $shipped = buyerOrder($buyer, 'shipped');

    Livewire::actingAs($buyer)
        ->test('pages::user-orders')
        ->call('setStatus', 'dikirim')
        ->assertSet('status', 'dikirim')
        ->assertSee($shipped->order_number)
        ->assertDontSee($unpaid->order_number)
        ->call('setStatus', 'tidak-ada')
        ->assertSet('status', '')
        ->assertSee($unpaid->order_number);
});

it('reads the status tab from the url', function () {
    $buyer = User::factory()->create();
    $completed = buyerOrder($buyer, 'completed');
    $unpaid = buyerOrder($buyer, 'pending_payment');

    $this->actingAs($buyer)
        ->get(route('user.orders', ['status' => 'selesai']))
        ->assertOk()
        ->assertSee($completed->order_number)
        ->assertDontSee($unpaid->order_number);
});

it('reminds the buyer of the payment deadline on unpaid orders', function () {
    $buyer = User::factory()->create();
    $order = buyerOrder($buyer, 'pending_payment', ['expires_at' => now()->addHours(5)]);

    $this->actingAs($buyer)
        ->get(route('user.orders'))
        ->assertSee('Bayar sebelum '.$order->expires_at->format('d M Y, H:i'));
});

it('shows the order detail with packages, items, and tracking', function () {
    $buyer = User::factory()->create();
    $order = buyerOrder($buyer, 'shipped', ['paid_at' => now()->subDays(2)]);
    $storeOrder = $order->storeOrders()->first();
    $storeOrder->update(['status' => 'shipped', 'shipped_at' => now()->subDay()]);
    $shipment = Shipment::factory()->for($storeOrder)->inTransit()->create(['tracking_number' => 'JNE123456789']);

    $this->actingAs($buyer)
        ->get(route('user.orders.show', $order))
        ->assertOk()
        ->assertSeeInOrder(['Beranda', 'Akun', 'Pesanan', $order->order_number])
        ->assertSee('Dalam Perjalanan')
        ->assertSee($storeOrder->store->name)
        ->assertSee('JNE123456789')
        ->assertSee('Barang '.$order->order_number)
        ->assertSee('aria-current="step"', false);
});

it('shows payment instructions for an unpaid order', function () {
    $buyer = User::factory()->create();
    $order = buyerOrder($buyer, 'pending_payment');
    Payment::factory()->for($order)->virtualAccount()->create();

    $this->actingAs($buyer)
        ->get(route('user.orders.show', $order))
        ->assertSee('Menunggu pembayaran')
        ->assertSee('Virtual Account');
});

it('explains a cancelled order instead of showing progress', function () {
    $buyer = User::factory()->create();
    $order = buyerOrder($buyer, 'expired');

    $this->actingAs($buyer)
        ->get(route('user.orders.show', $order))
        ->assertSee('Kedaluwarsa')
        ->assertSee('Batas waktu pembayaran sudah lewat')
        ->assertDontSee('aria-current="step"', false);
});

it('hides other buyers\' orders behind a 404', function () {
    $order = buyerOrder(User::factory()->create(), 'paid');

    $this->actingAs(User::factory()->create())
        ->get(route('user.orders.show', $order))
        ->assertNotFound();
});

it('keeps guests and staff away from order tracking', function () {
    $order = buyerOrder(User::factory()->create(), 'paid');

    $this->get(route('user.orders.show', $order))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->customerService()->create())
        ->get(route('user.orders.show', $order))
        ->assertRedirect('/');
});

it('opens the product page when a product photo in the order is tapped', function () {
    $buyer = User::factory()->create();
    $order = buyerOrder($buyer, 'paid');
    $product = Product::factory()->create();
    $order->items()->first()->update(['product_id' => $product->id]);

    $productLink = 'href="'.route('products.show', $product).'"';

    $this->actingAs($buyer)
        ->get(route('user.orders.show', $order))
        ->assertSee($productLink, false);

    // Produk yang sudah tidak dijual tidak punya halaman, jadi fotonya bukan link.
    $product->update(['is_active' => false]);

    $this->actingAs($buyer)
        ->get(route('user.orders.show', $order))
        ->assertDontSee($productLink, false);
});
