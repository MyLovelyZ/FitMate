<?php

/**
 * Wishlist: simpan/lepas produk dari halaman produk, lalu lihat di /user/wishlist.
 */

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->product = ProductVariant::factory()->create()->product;
});

it('saves and removes a product from the product page', function () {
    $buyer = User::factory()->create();

    Livewire::actingAs($buyer)
        ->test('pages::detail-product', ['product' => $this->product])
        ->assertSee('Simpan ke Wishlist')
        ->call('toggleWishlist')
        ->assertDispatched('notify')
        ->assertSee('Tersimpan di Wishlist');

    expect($buyer->hasInWishlist($this->product))->toBeTrue();

    Livewire::actingAs($buyer)
        ->test('pages::detail-product', ['product' => $this->product])
        ->call('toggleWishlist')
        ->assertSee('Simpan ke Wishlist');

    expect($buyer->fresh()->wishlist()->count())->toBe(0);
});

it('sends guests to the login page first', function () {
    Livewire::test('pages::detail-product', ['product' => $this->product])
        ->call('toggleWishlist')
        ->assertRedirect(route('login'));
});

it('hides the wishlist button from staff accounts', function () {
    Livewire::actingAs(User::factory()->customerService()->create())
        ->test('pages::detail-product', ['product' => $this->product])
        ->assertDontSee('Simpan ke Wishlist');
});

it('lists saved products, newest first, and removes them', function () {
    $buyer = User::factory()->create();
    $older = ProductVariant::factory()->create()->product;
    $buyer->wishlist()->attach($older->id, ['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
    $buyer->toggleWishlist($this->product);

    Livewire::actingAs($buyer)
        ->test('pages::user-wishlist')
        ->assertSeeInOrder([$this->product->name, $older->name])
        ->call('remove', $older->id)
        ->assertDontSee($older->name)
        ->assertSee($this->product->name);
});

it('keeps unavailable products visible with a notice', function () {
    $buyer = User::factory()->create();
    $buyer->toggleWishlist($this->product);
    $this->product->update(['is_active' => false]);

    Livewire::actingAs($buyer)
        ->test('pages::user-wishlist')
        ->assertSee($this->product->name)
        ->assertSee('Tidak tersedia');
});

it('shows the wishlist in the account menu and only to shoppers', function () {
    expect(User::factory()->create()->accountLinks())->toHaveKey('Wishlist');

    $this->actingAs(User::factory()->create())->get(route('user.wishlist'))->assertOk()->assertSee('Wishlist masih kosong');
    $this->actingAs(User::factory()->admin()->create())->get(route('user.wishlist'))->assertRedirect('/');
});

it('removes wishlist rows when the product is deleted for good', function () {
    $buyer = User::factory()->create();
    $buyer->toggleWishlist($this->product);

    Product::whereKey($this->product->id)->forceDelete();

    expect($buyer->wishlist()->count())->toBe(0);
});
