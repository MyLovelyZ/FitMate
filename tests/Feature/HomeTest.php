<?php

/**
 * Menjaga isi halaman beranda "Estética Archive" beserta kerangka layout-nya
 * (navbar + footer) tetap utuh.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('menampilkan hero, kategori, dan ethos di beranda', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Go-To Style Mate', false)
        ->assertSee('Shop the Archive')
        ->assertSee('Menswear')
        ->assertSee('Womenswear')
        ->assertSee('Accessories')
        ->assertSee('Discover Our Story')
        ->assertSee('id="categories"', false)
        ->assertSee('id="ethos"', false);
});

it('memakai foto untuk hero dan kartu kategori', function () {
    $images = [
        'images/landing/hero-wide.webp',
        'images/landing/hero-tall.webp',
        'images/landing/menswear.webp',
        'images/landing/womenswear.webp',
        'images/products/tas-ransel-daypack-1.webp',
    ];

    $response = $this->get(route('home'))->assertOk();

    foreach ($images as $image) {
        expect(public_path($image))->toBeFile();
        $response->assertSee(asset($image), false);
    }
});

it('memakai token tema dan font Estética di layout', function () {
    $this->get(route('home'))
        ->assertSee('bg-bg font-sans', false)
        ->assertSee('font-display', false)
        ->assertSee('FITMATE ARCHIVE. ALL RIGHTS RESERVED.', false);
});

it('menawarkan masuk dan daftar di navbar untuk tamu', function () {
    $this->get(route('home'))
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false)
        ->assertDontSee(route('logout'), false);
});

it('menawarkan keluar di navbar untuk pengguna yang sudah masuk', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertSee(route('logout'), false)
        ->assertSee('Keluar dari '.$user->name, false);
});

it('memasang bar loading request dan wadah notifikasi di layout', function () {
    $this->get(route('home'))
        ->assertSee('data-request-bar', false)
        ->assertSee('x-on:notify.window', false);

    expect(config('livewire.navigate.show_progress_bar'))->toBeTrue()
        ->and(config('livewire.navigate.progress_bar_color'))->toBe('var(--color-ink)');
});
