<?php

/**
 * Komplain pembeli setelah paket dikirim: dana escrow ditahan, CS meninjau,
 * lalu memutuskan refund ke pembeli atau pencairan ke penjual.
 */

use App\Models\Dispute;
use App\Models\EscrowHold;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->buyer = User::factory()->create();
    ['order' => $this->order, 'package' => $this->package] = shippedOrderFor($this->buyer);
    $this->cs = User::factory()->customerService()->create();
});

function fileComplaint(): void
{
    Livewire::actingAs(test()->buyer)
        ->test('pages::user-order-detail', ['order' => test()->order])
        ->set('disputeReason', 'damaged')
        ->set('disputeDescription', 'Jahitan lengan kiri lepas saat paket dibuka.')
        ->call('openDispute', test()->package->id)
        ->assertHasNoErrors();
}

it('lets the buyer file a complaint on a shipped package', function () {
    Livewire::actingAs($this->buyer)
        ->test('pages::user-order-detail', ['order' => $this->order])
        ->assertSee('Ajukan komplain');

    fileComplaint();

    $dispute = Dispute::sole();

    expect($dispute->status)->toBe('open')
        ->and($dispute->reason)->toBe('damaged')
        ->and($this->package->fresh()->status)->toBe('disputed')
        ->and($this->package->escrowHold->status)->toBe('held');

    Livewire::actingAs($this->buyer)
        ->test('pages::user-order-detail', ['order' => $this->order->fresh()])
        ->assertSee('Komplain: Barang rusak')
        ->assertDontSee('Ajukan komplain')
        ->assertDontSee('Pesanan Diterima');
});

it('validates the complaint', function () {
    Livewire::actingAs($this->buyer)
        ->test('pages::user-order-detail', ['order' => $this->order])
        ->set('disputeReason', 'alasan-asing')
        ->set('disputeDescription', 'pendek')
        ->call('openDispute', $this->package->id)
        ->assertHasErrors(['disputeReason', 'disputeDescription']);

    expect(Dispute::count())->toBe(0);
});

it('does not release the escrow automatically while the dispute is open', function () {
    $this->package->markDelivered();
    fileComplaint();
    $this->travel(30)->days();

    expect(EscrowHold::releaseDue())->toBe(0)
        ->and($this->package->fresh()->escrowHold->status)->toBe('held');
});

it('lets CS refund the buyer', function () {
    fileComplaint();
    $dispute = Dispute::sole();

    Livewire::actingAs($this->cs)
        ->test('pages::cs-ticket-detail', ['dispute' => $dispute])
        ->assertSee('Jahitan lengan kiri lepas')
        ->call('markUnderReview')
        ->call('resolve', 'buyer')
        ->assertHasErrors('resolutionNote')
        ->set('resolutionNote', 'Foto pembeli menunjukkan kerusakan.')
        ->call('resolve', 'buyer')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $dispute->refresh();
    $package = $this->package->fresh();

    expect($dispute->status)->toBe('resolved_buyer')
        ->and($dispute->resolved_by)->toBe($this->cs->id)
        ->and($package->status)->toBe('refunded')
        ->and($package->escrowHold->status)->toBe('refunded')
        ->and((float) Wallet::forUser($this->buyer)->balance_available)->toBe((float) $package->total)
        ->and($this->order->fresh()->status)->toBe('refunded');
});

it('lets CS side with the seller and release the money', function () {
    fileComplaint();
    $dispute = Dispute::sole();

    Livewire::actingAs($this->cs)
        ->test('pages::cs-ticket-detail', ['dispute' => $dispute])
        ->set('resolutionNote', 'Resi menunjukkan paket diterima utuh.')
        ->call('resolve', 'seller')
        ->assertHasNoErrors();

    $package = $this->package->fresh();

    expect($dispute->fresh()->status)->toBe('resolved_seller')
        ->and($package->status)->toBe('completed')
        ->and($package->escrowHold->status)->toBe('released')
        ->and($package->escrowHold->release_reason)->toBe('dispute_resolved');

    Livewire::actingAs($this->cs)
        ->test('pages::cs-ticket-detail', ['dispute' => $dispute->fresh()])
        ->assertDontSee('Setujui · Refund Pembeli');
});

it('refuses to resolve a ticket twice', function () {
    fileComplaint();
    $dispute = Dispute::sole();
    $dispute->resolve($this->cs, 'seller', 'Paket utuh.');

    expect(fn () => $dispute->fresh()->resolve($this->cs, 'buyer', 'Ubah pikiran.'))->toThrow(LogicException::class);
});

it('lists tickets with a link to the detail page for CS only', function () {
    fileComplaint();
    $dispute = Dispute::sole();

    $this->actingAs($this->cs)->get(route('cs.tickets.index'))->assertOk()->assertSee(route('cs.tickets.show', $dispute))->assertSee('Barang rusak');
    $this->actingAs($this->cs)->get(route('cs.tickets.show', $dispute))->assertOk();
    $this->actingAs($this->buyer)->get(route('cs.tickets.show', $dispute))->assertRedirect('/');
});

it('shows the complaint to the seller', function () {
    fileComplaint();

    Livewire::actingAs($this->package->store->owner)
        ->test('pages::seller-order-detail', ['storeOrder' => $this->package->fresh()])
        ->assertSee('Komplain pembeli: Barang rusak')
        ->assertSee('CS FitMate sedang meninjau');
});
