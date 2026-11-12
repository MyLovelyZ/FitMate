<?php

/**
 * Seller mengubah status pesanan (Dikemas, Dikirim, Sudah Sampai, Dibatalkan)
 * dari daftar Seller Center, dan pembeli menerima notifikasi untuk setiap
 * perubahan itu di /user/notifications.
 */

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\PackageStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->buyer = User::factory()->create();
    ['order' => $this->order, 'package' => $this->package, 'variant' => $this->variant] = paidOrderFor($this->buyer);
    $this->seller = $this->package->store->owner;
});

/**
 * Pesanan baru yang belum dibayar, berisi 1 barang dari varian test.
 */
function unpaidOrderFor(User $buyer): Order
{
    Cart::forUser($buyer)->items()->create(['product_variant_id' => test()->variant->id, 'quantity' => 1]);

    return Order::placeFromCart($buyer, [
        'recipient_name' => 'Rani',
        'recipient_phone' => '081200000001',
        'street' => 'Jl. Asia Afrika 2',
        'city' => 'Bandung',
        'state' => 'Jawa Barat',
        'postal_code' => '40111',
    ], 'jne-reg', 'qris');
}

/**
 * Membuka form "Ubah Status" paket test di daftar pesanan seller lalu menyimpannya.
 *
 * @param  array<string, string>  $fields
 */
function changeStatus(string $status, array $fields = []): Testable
{
    $component = Livewire::actingAs(test()->seller)
        ->test('pages::seller-orders')
        ->call('editStatus', test()->package->id)
        ->set('newStatus', $status);

    foreach ($fields as $name => $value) {
        $component->set($name, $value);
    }

    return $component->call('saveStatus');
}

it('offers only the statuses that make sense right now', function () {
    expect($this->package->sellerStatusOptions())->toBe(['processing' => 'Dikemas', 'cancelled' => 'Dibatalkan']);

    $this->package->process();
    expect($this->package->fresh()->sellerStatusOptions())->toBe(['shipped' => 'Dikirim', 'cancelled' => 'Dibatalkan']);

    $this->package->fresh()->ship('JNE0001');
    expect($this->package->fresh()->sellerStatusOptions())->toBe(['delivered' => 'Sudah Sampai']);

    expect(unpaidOrderFor(User::factory()->create())->storeOrders()->sole()->sellerStatusOptions())->toBe([]);
});

it('lets the seller walk an order through dikemas, dikirim and sudah sampai from the list', function () {
    changeStatus('processing')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSet('editingId', null);

    expect($this->package->fresh()->status)->toBe('processing');

    changeStatus('shipped')->assertHasErrors(['trackingNumber' => 'required']);
    changeStatus('shipped', ['trackingNumber' => 'JNE778899'])->assertHasNoErrors();

    expect($this->package->fresh()->status)->toBe('shipped')
        ->and($this->package->fresh()->shipment->tracking_number)->toBe('JNE778899');

    changeStatus('delivered')->assertHasNoErrors();

    expect($this->package->fresh()->status)->toBe('delivered')
        ->and($this->buyer->notifications()->get()->pluck('data.status')->all())
        ->toEqualCanonicalizing(['processing', 'shipped', 'delivered']);
});

it('cancels with a reason, restocks and refunds the buyer', function () {
    changeStatus('cancelled')->assertHasErrors(['cancelReason' => 'required']);
    changeStatus('cancelled', ['cancelReason' => 'Stok ternyata rusak'])->assertHasNoErrors();

    $package = $this->package->fresh();
    $notification = $this->buyer->notifications()->sole();

    expect($package->status)->toBe('cancelled')
        ->and($this->variant->fresh()->stock)->toBe(5)
        ->and((float) Wallet::forUser($this->buyer)->balance_available)->toBe((float) $package->total)
        ->and($notification->data['title'])->toBe('Pesanan dibatalkan')
        ->and($notification->data['message'])->toContain('Dibatalkan penjual: Stok ternyata rusak')
        ->and($notification->data['message'])->toContain('dikembalikan ke saldo');
});

it('rejects a status that is not allowed from the current one', function () {
    changeStatus('delivered')->assertHasErrors(['newStatus' => 'in']);

    expect($this->package->fresh()->status)->toBe('pending')
        ->and($this->buyer->notifications()->count())->toBe(0);

    expect(fn () => $this->package->changeStatusBySeller('delivered', $this->seller))->toThrow(LogicException::class);
});

it('does not let a seller change another store package', function () {
    $otherSeller = User::factory()->seller()->create();

    Livewire::actingAs($otherSeller)
        ->test('pages::seller-orders')
        ->call('editStatus', $this->package->id)
        ->assertNotFound();

    expect(fn () => $this->package->changeStatusBySeller('processing', $otherSeller))->toThrow(LogicException::class);
});

it('stores a copy of the package details in the notification', function () {
    $this->package->process();

    expect($this->buyer->notifications()->sole()->data)->toMatchArray([
        'store_order_id' => $this->package->id,
        'order_id' => $this->order->id,
        'order_number' => $this->order->order_number,
        'store_order_number' => $this->package->store_order_number,
        'status' => 'processing',
        'title' => PackageStatusChanged::TITLES['processing'],
    ]);
});

it('does not notify the buyer about a cancellation they made themselves', function () {
    $buyer = User::factory()->create();
    $order = unpaidOrderFor($buyer);

    Livewire::actingAs($buyer)
        ->test('pages::user-order-detail', ['order' => $order])
        ->call('cancelOrder');

    expect($order->fresh()->status)->toBe('cancelled')
        ->and($buyer->notifications()->count())->toBe(0);

    // Batal otomatis karena lewat batas bayar tetap dikabarkan.
    unpaidOrderFor($buyer)->cancelUnpaid('expired');

    expect($buyer->notifications()->sole()->data['message'])->toContain('Batas waktu pembayaran lewat.');
});

it('shows the notifications to the buyer with an unread badge in the navbar', function () {
    $this->package->process();
    $this->package->fresh()->ship('JNE0001');

    $this->actingAs($this->buyer)
        ->get(route('user.notifications'))
        ->assertOk()
        ->assertSee('Pesanan sedang dikemas')
        ->assertSee('Pesanan dikirim')
        ->assertSee('JNE0001')
        ->assertSee('Notifikasi, 2 belum dibaca');

    Livewire::actingAs($this->buyer)
        ->test('pages::user-notifications')
        ->set('filter', 'unread')
        ->assertSee('Pesanan dikirim')
        ->call('markAllAsRead')
        ->assertDispatched('notify')
        ->assertSee('Semua sudah dibaca');

    expect($this->buyer->unreadNotifications()->count())->toBe(0);
});

it('opens the order from a notification and marks it read', function () {
    $this->package->process();
    $notification = $this->buyer->notifications()->sole();

    Livewire::actingAs($this->buyer)
        ->test('pages::user-notifications')
        ->call('open', $notification->id)
        ->assertRedirect(route('user.orders.show', $this->order));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks the order notifications read when the buyer opens the order', function () {
    $this->package->process();

    Livewire::actingAs($this->buyer)->test('pages::user-order-detail', ['order' => $this->order]);

    expect($this->buyer->unreadNotifications()->count())->toBe(0);
});

it('keeps notifications private to their owner', function () {
    $this->package->process();
    $notification = $this->buyer->notifications()->sole();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::user-notifications')
        ->assertDontSee('Pesanan sedang dikemas')
        ->call('open', $notification->id)
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});
