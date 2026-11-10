<?php

/**
 * Seller memperbarui status pengiriman paket yang sudah diserahkan ke kurir;
 * setiap update tercatat di riwayat pelacakan yang dilihat pembeli.
 */

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->buyer = User::factory()->create();
    ['order' => $this->order, 'package' => $this->package, 'variant' => $this->variant] = shippedOrderFor($this->buyer);
    $this->seller = $this->package->store->owner;
});

function sellerOrderPage(): Testable
{
    return Livewire::actingAs(test()->seller)->test('pages::seller-order-detail', ['storeOrder' => test()->package]);
}

it('records the hand-over to the courier as the first tracking event', function () {
    $shipment = $this->package->shipment;

    expect($shipment->status)->toBe('picked_up')
        ->and($shipment->events)->toHaveCount(1)
        ->and($shipment->events->first()->status)->toBe('picked_up')
        ->and($shipment->events->first()->description)->toContain('JNE0001');
});

it('lets the seller add in-transit checkpoints and shows them to the buyer', function () {
    sellerOrderPage()
        ->set('shipmentStatus', 'in_transit')
        ->set('shipmentLocation', 'Gudang JNE Bandung')
        ->set('shipmentNote', 'Paket tiba di gudang transit.')
        ->call('updateShipment')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSet('shipmentStatus', '')
        ->assertSee('Gudang JNE Bandung');

    $shipment = $this->package->fresh()->shipment;
    $latest = $shipment->events->first();

    expect($shipment->status)->toBe('in_transit')
        ->and($shipment->events)->toHaveCount(2)
        ->and($latest->description)->toBe('Paket tiba di gudang transit.')
        ->and($latest->location)->toBe('Gudang JNE Bandung')
        ->and($latest->recorded_by)->toBe($this->seller->id)
        ->and($this->package->fresh()->status)->toBe('shipped');

    Livewire::actingAs($this->buyer)
        ->test('pages::user-order-detail', ['order' => $this->order])
        ->assertSee('Lacak Paket')
        ->assertSee('Paket tiba di gudang transit.')
        ->assertSee('Gudang JNE Bandung');

    $this->actingAs($this->buyer)
        ->get(route('orders.invoice', $this->order))
        ->assertOk()
        ->assertSee('Paket tiba di gudang transit.');
});

it('marks the package delivered and starts the escrow countdown', function () {
    sellerOrderPage()
        ->set('shipmentStatus', 'delivered')
        ->call('updateShipment')
        ->assertHasNoErrors();

    $package = $this->package->fresh();

    expect($package->status)->toBe('delivered')
        ->and($package->shipment->status)->toBe('delivered')
        ->and($package->shipment->events->first()->status)->toBe('delivered')
        ->and($package->escrowHold->auto_release_at->isFuture())->toBeTrue();
});

it('requires a reason for a failed delivery and allows a retry afterwards', function () {
    sellerOrderPage()
        ->set('shipmentStatus', 'failed')
        ->call('updateShipment')
        ->assertHasErrors(['shipmentNote' => 'required_if'])
        ->set('shipmentNote', 'Penerima tidak di rumah.')
        ->call('updateShipment')
        ->assertHasNoErrors();

    $shipment = $this->package->fresh()->shipment;

    expect($shipment->status)->toBe('failed')
        ->and($shipment->nextStatuses())->toHaveKeys(['in_transit', 'returned'])
        ->and($shipment->nextStatuses())->not->toHaveKey('delivered');

    sellerOrderPage()
        ->set('shipmentStatus', 'in_transit')
        ->call('updateShipment')
        ->assertHasNoErrors();

    expect($this->package->fresh()->shipment->status)->toBe('in_transit');
});

it('returns the package to the seller: stock back and the buyer refunded', function () {
    $this->package->updateShipmentStatus('failed', $this->seller, 'Alamat tidak ditemukan.');

    sellerOrderPage()
        ->set('shipmentStatus', 'returned')
        ->set('shipmentNote', 'Alamat tidak ditemukan setelah 3 kali percobaan.')
        ->call('updateShipment')
        ->assertHasNoErrors();

    $package = $this->package->fresh();

    expect($package->status)->toBe('refunded')
        ->and($package->shipment->status)->toBe('returned')
        ->and($package->escrowHold->status)->toBe('refunded')
        ->and($this->variant->fresh()->stock)->toBe(5)
        ->and((float) Wallet::forUser($this->buyer)->balance_available)->toBe((float) $package->total)
        ->and($this->order->fresh()->status)->toBe('refunded');
});

it('rejects shipment statuses that are not allowed from the current one', function () {
    sellerOrderPage()
        ->set('shipmentStatus', 'returned')
        ->set('shipmentNote', 'Coba kembalikan langsung.')
        ->call('updateShipment')
        ->assertHasErrors(['shipmentStatus' => 'in']);

    expect(fn () => $this->package->fresh()->updateShipmentStatus('pending', $this->seller))
        ->toThrow(LogicException::class);

    expect($this->package->fresh()->shipment->status)->toBe('picked_up');
});

it('blocks shipment updates once the package is no longer on the road', function () {
    $this->package->complete('buyer_confirmed');

    sellerOrderPage()
        ->call('updateShipment')
        ->assertForbidden();

    $shipment = $this->package->fresh()->shipment;

    expect($shipment->status)->toBe('delivered')
        ->and($shipment->events->first()->description)->toBe('Pembeli mengonfirmasi pesanan sudah diterima.');
});

it('lets the seller correct a mistyped tracking number while shipped', function () {
    sellerOrderPage()
        ->set('trackingNumber', 'JNE0002')
        ->call('correctTrackingNumber')
        ->assertHasNoErrors();

    $shipment = $this->package->fresh()->shipment;

    expect($shipment->tracking_number)->toBe('JNE0002')
        ->and($shipment->events->first()->description)->toBe('Nomor resi diperbarui menjadi JNE0002.');
});

it('hides another store package from the seller', function () {
    $otherSeller = User::factory()->seller()->create();

    Livewire::actingAs($otherSeller)
        ->test('pages::seller-order-detail', ['storeOrder' => $this->package])
        ->assertNotFound();
});
