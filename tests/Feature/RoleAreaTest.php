<?php

/**
 * Halaman area per role: setiap role bisa membuka halamannya sendiri, ditolak
 * di area role lain, dan diarahkan ke dashboard yang benar setelah login.
 */

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

dataset('role pages', [
    'admin dashboard' => ['admin', 'admin.dashboard'],
    'admin users' => ['admin', 'admin.users.index'],
    'admin products' => ['admin', 'admin.products.index'],
    'admin vouchers' => ['admin', 'admin.vouchers.index'],
    'superadmin in admin users' => ['superAdmin', 'admin.users.index'],
    'superadmin in admin vouchers' => ['superAdmin', 'admin.vouchers.index'],
    'cs dashboard' => ['customerService', 'cs.dashboard'],
    'cs chats' => ['customerService', 'cs.chats.index'],
    'cs tickets' => ['customerService', 'cs.tickets.index'],
    'seller dashboard' => ['seller', 'seller.dashboard'],
    'seller products' => ['seller', 'seller.products.index'],
    'seller orders' => ['seller', 'seller.orders.index'],
    'superadmin settings' => ['superAdmin', 'superadmin.settings'],
    'user orders' => [null, 'user.orders'],
    'user cart' => [null, 'user.cart'],
]);

it('renders each role page for its own role', function (?string $state, string $routeName) {
    $factory = User::factory();
    $user = ($state ? $factory->{$state}() : $factory)->create();

    $this->actingAs($user)->get(route($routeName))->assertOk();
})->with('role pages');

it('sends guests to the login page', function (?string $state, string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with('role pages');

it('keeps other roles out of a role area', function (string $routeName) {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->get(route($routeName))->assertRedirect('/');
})->with(['admin.dashboard', 'admin.users.index', 'admin.products.index', 'admin.vouchers.index', 'cs.dashboard', 'seller.dashboard', 'superadmin.settings']);

it('keeps admins out of the superadmin settings', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('superadmin.settings'))
        ->assertRedirect('/');
});

it('keeps staff out of the buyer area', function () {
    $this->actingAs(User::factory()->customerService()->create())
        ->get(route('user.cart'))
        ->assertRedirect('/');
});

it('redirects the superadmin dashboard to the user list', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('superadmin.dashboard'))
        ->assertRedirect(route('admin.users.index'));
});

it('sends each role to its own dashboard after login', function (?string $state, string $routeName) {
    $factory = User::factory();
    $user = ($state ? $factory->{$state}() : $factory)->create(['password' => Hash::make('rahasia123')]);

    Livewire::test('pages::login')
        ->set('email', $user->email)
        ->set('password', 'rahasia123')
        ->call('login')
        ->assertRedirect(route($routeName));
})->with([
    'superadmin' => ['superAdmin', 'superadmin.dashboard'],
    'admin' => ['admin', 'admin.dashboard'],
    'seller' => ['seller', 'seller.dashboard'],
    'customer service' => ['customerService', 'cs.dashboard'],
    'buyer' => [null, 'home'],
]);

it('shows a seller only the products and orders of their own store', function () {
    $store = Store::factory()->create();
    $ownProduct = Product::factory()->for($store)->create();
    $otherProduct = Product::factory()->create();
    $ownOrder = StoreOrder::factory()->for($store)->create();
    $otherOrder = StoreOrder::factory()->create();

    $this->actingAs($store->owner);

    $this->get(route('seller.products.index'))
        ->assertSee($ownProduct->name)
        ->assertDontSee($otherProduct->name);

    $this->get(route('seller.orders.index'))
        ->assertSee($ownOrder->store_order_number)
        ->assertDontSee($otherOrder->store_order_number);
});

it('lists open tickets for customer service', function () {
    $ticket = Dispute::factory()->create();

    $this->actingAs(User::factory()->customerService()->create())
        ->get(route('cs.tickets.index'))
        ->assertSee($ticket->storeOrder->store_order_number);
});

it('shows a buyer only their own orders', function () {
    $buyer = User::factory()->create();
    $ownOrder = Order::factory()->for($buyer)->create();
    $otherOrder = Order::factory()->create();

    $this->actingAs($buyer)
        ->get(route('user.orders'))
        ->assertSee($ownOrder->order_number)
        ->assertDontSee($otherOrder->order_number);
});

it('loads the admin dashboard stats lazily behind a skeleton', function () {
    $pendingStore = Store::factory()->pending()->create(['name' => 'Toko Menunggu Verifikasi Uji']);
    $admin = User::factory()->admin()->create();

    // Request halaman pertama hanya berisi kerangka; angkanya belum dihitung.
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Admin')
        ->assertDontSee($pendingStore->name);

    $component = Livewire::actingAs($admin)->test('pages::admin-dashboard');

    expect(loadIsland($component, 'stats'))->toContain('Sengketa Terbuka')
        ->and(loadIsland($component, 'pending-stores'))->toContain($pendingStore->name);
});

it('shows breadcrumbs on role pages', function () {
    $this->actingAs(User::factory()->seller()->create())
        ->get(route('seller.products.index'))
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSeeInOrder(['Beranda', 'Seller', 'Produk'])
        ->assertSee('"@type":"BreadcrumbList"', false);
});

it('removes an item from the buyer cart but not from someone else\'s', function () {
    $buyer = User::factory()->create();
    $ownItem = CartItem::factory()->for(Cart::factory()->for($buyer))->create();
    $otherItem = CartItem::factory()->create();

    Livewire::actingAs($buyer)
        ->test('pages::user-cart')
        ->assertSee($ownItem->productVariant->product->name)
        ->call('remove', $ownItem->id)
        ->call('remove', $otherItem->id);

    expect(CartItem::find($ownItem->id))->toBeNull()
        ->and(CartItem::find($otherItem->id))->not->toBeNull();
});
