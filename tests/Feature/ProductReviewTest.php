<?php

/**
 * Ulasan & rating: hanya pembeli yang paketnya selesai, sekali per barang, dan
 * ringkasannya (rata-rata bintang + umpan balik ukuran) tampil di halaman produk.
 */

use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->buyer = User::factory()->create(['name' => 'Rani Pembeli']);
    ['order' => $this->order, 'package' => $this->package, 'variant' => $this->variant] = shippedOrderFor($this->buyer);
    $this->item = $this->package->items()->sole();
});

it('opens the review form only after the package is completed', function () {
    Livewire::actingAs($this->buyer)
        ->test('pages::user-order-detail', ['order' => $this->order])
        ->assertDontSee('Beri Ulasan');

    $this->package->complete('buyer_confirmed');

    Livewire::actingAs($this->buyer)
        ->test('pages::user-order-detail', ['order' => $this->order->fresh()])
        ->assertSee('Beri Ulasan');
});

it('lets the buyer rate an item once with size feedback', function () {
    $this->package->complete('buyer_confirmed');

    $component = Livewire::actingAs($this->buyer)
        ->test('pages::user-order-detail', ['order' => $this->order->fresh()])
        ->set('rating', 4)
        ->set('fitFeedback', 'too_small')
        ->set('reviewComment', 'Bahannya bagus, tapi agak sempit di bahu.')
        ->call('submitReview', $this->item->id)
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertDontSee('Beri Ulasan');

    $review = ProductReview::sole();

    expect($review->rating)->toBe(4)
        ->and($review->fit_feedback)->toBe('too_small')
        ->and($review->product_id)->toBe($this->variant->product_id)
        ->and($review->user_id)->toBe($this->buyer->id);

    $component->call('submitReview', $this->item->id)->assertHasErrors('review');

    expect(ProductReview::count())->toBe(1);
});

it('validates the rating', function () {
    $this->package->complete('buyer_confirmed');

    Livewire::actingAs($this->buyer)
        ->test('pages::user-order-detail', ['order' => $this->order->fresh()])
        ->set('rating', 7)
        ->call('submitReview', $this->item->id)
        ->assertHasErrors('rating');

    expect(ProductReview::count())->toBe(0);
});

it('refuses reviews from other buyers and before completion', function () {
    expect(fn () => ProductReview::submitFor($this->item, $this->buyer, 5, null, null))
        ->toThrow(ValidationException::class);

    $this->package->complete('buyer_confirmed');

    expect(fn () => ProductReview::submitFor($this->item->fresh(), User::factory()->create(), 5, null, null))
        ->toThrow(ValidationException::class);
});

it('shows the average rating, size feedback, and reviews on the product page', function () {
    $this->package->complete('buyer_confirmed');
    ProductReview::submitFor($this->item->fresh(), $this->buyer, 5, 'fit', 'Pas banget!');
    ProductReview::factory()->create(['product_id' => $this->variant->product_id, 'rating' => 4, 'fit_feedback' => 'fit']);

    $summary = $this->variant->product->ratingSummary();

    expect($summary['average'])->toBe(4.5)
        ->and($summary['count'])->toBe(2)
        ->and($summary['topFit'])->toBe('fit');

    Livewire::test('pages::detail-product', ['product' => $this->variant->product])
        ->assertSee('Ulasan Pembeli')
        ->assertSee('4,5')
        ->assertSee('(2 ulasan)')
        ->assertSee('Kebanyakan bilang ukurannya pas')
        ->assertSee('Rani Pembeli')
        ->assertSee('Pas banget!');
});

it('shows an empty state when a product has no reviews', function () {
    Livewire::test('pages::detail-product', ['product' => $this->variant->product])
        ->assertSee('Belum ada ulasan');
});
