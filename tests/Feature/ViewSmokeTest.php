<?php

/**
 * Memastikan setiap halaman masih bisa dirender.
 *
 * Test ini menangkap kesalahan yang tidak terlihat saat Blade dikompilasi:
 * nama route yang salah, komponen yang tidak ada, atau prop wajib yang lupa diisi.
 * Tambahkan nama route baru ke daftar di bawah setiap kali membuat halaman baru.
 */

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('bisa merender semua halaman', function (string $name) {
    $this->get(route($name))->assertOk();
})->with([
    'home',
    'products.index',
    'login',
    'register',
    'help',
    'stores.create',
]);

it('bisa merender halaman yang butuh parameter route', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id]);

    $this->get(route('products.show', $product))->assertOk();
});

/**
 * Link internal di HTML (tanpa bagian #anchor), selain aset dan form logout.
 *
 * @return array<int, string>
 */
function internalLinks(string $html): array
{
    preg_match_all('/href="([^"#]*)/', $html, $matches);

    return collect($matches[1])
        ->map(fn (string $url): string => html_entity_decode($url))
        ->filter(fn (string $url): bool => str_starts_with($url, url('/')))
        ->reject(fn (string $url): bool => str_contains($url, '/build/') || str_ends_with($url, '.ico'))
        ->unique()
        ->values()
        ->all();
}

it('tidak punya link internal yang rusak di halaman publik', function (?string $role) {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id]);

    if ($role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
    }

    $pages = [route('home'), route('products.index'), route('products.show', $product), route('stores.show', $product->store)];

    foreach ($pages as $page) {
        foreach (internalLinks($this->get($page)->getContent()) as $link) {
            expect($this->get($link)->getStatusCode())->toBeLessThan(400, "Link {$link} di {$page} rusak.");
        }
    }
})->with(['tamu' => null, 'pembeli' => 'user', 'seller' => 'seller', 'cs' => 'customerservice', 'admin' => 'admin', 'superadmin' => 'superadmin']);
