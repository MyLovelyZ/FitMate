<?php

/**
 * Moderasi di area admin. Admin memblokir, menghapus, dan melihat barang
 * pelanggan & seller. Superadmin melakukan hal yang sama ke semua akun kecuali
 * sesama superadmin, dan bisa menaikkan pelanggan jadi admin atau CS.
 */

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create();
    $this->admin = User::factory()->admin()->create();
});

it('keeps sellers, CS, and buyers out of the moderation pages', function (?string $state) {
    $target = User::factory()->create();
    $user = ($state ? User::factory()->{$state}() : User::factory())->create();

    $this->actingAs($user)->get(route('admin.users.show', $target))->assertRedirect('/');
    $this->actingAs($user)->get(route('admin.products.index'))->assertRedirect('/');
})->with(['seller' => 'seller', 'cs' => 'customerService', 'pembeli' => null]);

it('sends the superadmin dashboard to the shared user list', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.dashboard'))
        ->assertRedirect(route('admin.users.index'));

    $this->actingAs($this->superAdmin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Pengaturan');
});

it('shows admins only customers and sellers, and superadmins every account', function () {
    User::factory()->create(['name' => 'Pembeli Biasa']);
    User::factory()->seller()->create(['name' => 'Seller Biasa']);
    User::factory()->customerService()->create(['name' => 'Staf CS']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin-users')
        ->assertSee('Pembeli Biasa')
        ->assertSee('Seller Biasa')
        ->assertDontSee('Staf CS')
        ->assertDontSee($this->superAdmin->email)
        ->set('role', 'customerservice')
        ->assertSee('Pembeli Biasa');

    Livewire::actingAs($this->superAdmin)
        ->test('pages::admin-users')
        ->assertSee('Staf CS')
        ->assertSee($this->admin->email);
});

it('lets an admin open customers and sellers but not staff accounts', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users.show', User::factory()->create()))
        ->assertOk();

    foreach ([User::factory()->customerService()->create(), User::factory()->admin()->create(), $this->superAdmin] as $staff) {
        $this->actingAs($this->admin)
            ->get(route('admin.users.show', $staff))
            ->assertForbidden();
    }
});

it('shows a user with the products their store sells', function () {
    $store = Store::factory()->create();
    Product::factory()->for($store)->create(['name' => 'Kemeja Linen Uji']);
    Product::factory()->for($store)->create(['name' => 'Celana Nonaktif Uji', 'is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('admin.users.show', $store->owner))
        ->assertOk()
        ->assertSee($store->owner->email)
        ->assertSee($store->name)
        ->assertSee('Kemeja Linen Uji')
        ->assertSee('Celana Nonaktif Uji')
        ->assertSee(route('admin.products.index', ['toko' => $store->id]), false)
        ->assertDontSee('Role Akun');
});

it('bans a seller: logged out, cannot log in, and the store disappears from the catalog', function (string $moderator) {
    $store = Store::factory()->create();
    $seller = $store->owner;
    $seller->update(['password' => Hash::make('rahasia123')]);
    $product = Product::factory()->for($store)->create();

    Livewire::actingAs($this->{$moderator})
        ->test('pages::admin-user-detail', ['user' => $seller])
        ->call('ban')
        ->assertHasErrors(['banReason' => 'required'])
        ->set('banReason', 'Menjual barang palsu berulang kali.')
        ->call('ban')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $seller->refresh();

    expect($seller->isBanned())->toBeTrue()
        ->and($seller->banned_by)->toBe($this->{$moderator}->id)
        ->and($store->fresh()->status)->toBe('suspended')
        ->and(Product::purchasable()->whereKey($product)->exists())->toBeFalse();

    // Sesi yang masih terbuka dikeluarkan pada request berikutnya.
    $this->actingAs($seller)
        ->get(route('seller.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Akunmu diblokir: Menjual barang palsu berulang kali.');
    $this->assertGuest();

    Livewire::test('pages::login')
        ->set('email', $seller->email)
        ->set('password', 'rahasia123')
        ->call('login')
        ->assertHasErrors('email')
        ->assertSee('Akun ini diblokir oleh FitMate: Menjual barang palsu berulang kali.');
    $this->assertGuest();
})->with(['admin', 'superAdmin']);

it('unbans a user without reactivating their store on its own', function () {
    $store = Store::factory()->create();
    $seller = $store->owner;
    $seller->ban($this->admin, 'Menjual barang palsu berulang kali.');

    Livewire::actingAs($this->admin)
        ->test('pages::admin-user-detail', ['user' => $seller])
        ->assertSee('Diblokir')
        ->call('unban')
        ->assertDispatched('notify');

    expect($seller->fresh()->isBanned())->toBeFalse()
        ->and($store->fresh()->status)->toBe('suspended');

    $this->actingAs($seller->fresh())->get(route('seller.dashboard'))->assertOk();
});

it('lets the superadmin ban staff but never a superadmin, including themselves', function (bool $self) {
    $cs = User::factory()->customerService()->create();

    Livewire::actingAs($this->superAdmin)
        ->test('pages::admin-user-detail', ['user' => $cs])
        ->set('banReason', 'Membocorkan data pelanggan.')
        ->call('ban')
        ->assertHasNoErrors();

    expect($cs->fresh()->isBanned())->toBeTrue();

    $target = $self ? $this->superAdmin : User::factory()->superAdmin()->create();

    Livewire::actingAs($this->superAdmin)
        ->test('pages::admin-user-detail', ['user' => $target])
        ->assertSee('Akun superadmin tidak bisa diblokir atau dihapus')
        ->set('banReason', 'Mencoba mengunci superadmin lain.')
        ->call('ban')
        ->assertForbidden();

    Livewire::actingAs($this->superAdmin)
        ->test('pages::admin-user-detail', ['user' => $target])
        ->call('deleteAccount')
        ->assertForbidden();

    expect($target->fresh())->not->toBeNull()
        ->and($target->fresh()->isBanned())->toBeFalse();
})->with(['dirinya sendiri' => true, 'superadmin lain' => false]);

it('promotes a customer to admin or CS and back, for the superadmin only', function () {
    $buyer = User::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test('pages::admin-user-detail', ['user' => $buyer])
        ->assertSee('Role Akun')
        ->set('role', 'admin')
        ->call('changeRole')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($buyer->fresh()->role)->toBe('admin');
    $this->actingAs($buyer->fresh())->get(route('admin.dashboard'))->assertOk();

    Livewire::actingAs($this->superAdmin)
        ->test('pages::admin-user-detail', ['user' => $buyer->fresh()])
        ->set('role', 'customerservice')
        ->call('changeRole');

    expect($buyer->fresh()->role)->toBe('customerservice');

    Livewire::actingAs($this->superAdmin)
        ->test('pages::admin-user-detail', ['user' => $buyer->fresh()])
        ->set('role', 'user')
        ->call('changeRole');

    expect($buyer->fresh()->role)->toBe('user');

    Livewire::actingAs($this->admin)
        ->test('pages::admin-user-detail', ['user' => $buyer->fresh()])
        ->set('role', 'admin')
        ->call('changeRole')
        ->assertForbidden();

    expect($buyer->fresh()->role)->toBe('user');
});

it('refuses roles that cannot be handed out and accounts that own a store', function () {
    $buyer = User::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test('pages::admin-user-detail', ['user' => $buyer])
        ->set('role', 'superadmin')
        ->call('changeRole')
        ->assertHasErrors(['role' => 'in']);

    $seller = Store::factory()->create()->owner;

    Livewire::actingAs($this->superAdmin)
        ->test('pages::admin-user-detail', ['user' => $seller])
        ->assertSee('Akun ini punya toko, jadi rolenya mengikuti status tokonya.')
        ->set('role', 'admin')
        ->call('changeRole')
        ->assertHasErrors('role');

    expect($buyer->fresh()->role)->toBe('user')
        ->and($seller->fresh()->role)->toBe('seller');
});

it('deletes an account without transactions together with its store and products', function () {
    Storage::fake(Store::IDENTITY_DISK);
    Storage::fake('public');

    $store = Store::factory()->withIdentityCard('identity-cards/ktp-hapus.jpg')->create(['logo' => 'stores/logos/logo-hapus.png']);
    Storage::disk(Store::IDENTITY_DISK)->put('identity-cards/ktp-hapus.jpg', UploadedFile::fake()->image('ktp.jpg')->getContent());
    Storage::disk('public')->put('stores/logos/logo-hapus.png', UploadedFile::fake()->image('logo.png')->getContent());
    $variant = ProductVariant::factory()->for(Product::factory()->for($store))->create();
    $owner = $store->owner;

    Livewire::actingAs($this->admin)
        ->test('pages::admin-user-detail', ['user' => $owner])
        ->call('deleteAccount')
        ->assertRedirect(route('admin.users.index'));

    expect(User::find($owner->id))->toBeNull()
        ->and(Store::withTrashed()->find($store->id))->toBeNull()
        ->and(Product::withTrashed()->find($variant->product_id))->toBeNull();

    Storage::disk(Store::IDENTITY_DISK)->assertMissing('identity-cards/ktp-hapus.jpg');
    Storage::disk('public')->assertMissing('stores/logos/logo-hapus.png');
});

it('refuses to delete an account that has order history and suggests banning instead', function () {
    $buyer = Order::factory()->create()->user;

    Livewire::actingAs($this->admin)
        ->test('pages::admin-user-detail', ['user' => $buyer])
        ->assertSee('Akun ini punya riwayat pesanan sebagai pembeli.')
        ->assertDontSee('Hapus Permanen')
        ->call('deleteAccount')
        ->assertNoRedirect();

    expect(User::find($buyer->id))->not->toBeNull()
        ->and(fn () => $buyer->deleteAccount())->toThrow(LogicException::class);
});

it('lists every product from every store, including hidden ones', function () {
    $active = Product::factory()->create(['name' => 'Jaket Tayang']);
    Product::factory()->create(['name' => 'Kaos Nonaktif', 'is_active' => false]);
    Product::factory()->for(Store::factory()->suspended())->create(['name' => 'Topi Toko Ditangguhkan']);
    Product::factory()->create(['name' => 'Sepatu Dihapus'])->delete();

    Livewire::actingAs($this->admin)
        ->test('pages::admin-products')
        ->assertSee('Jaket Tayang')
        ->assertSee('Kaos Nonaktif')
        ->assertSee('Topi Toko Ditangguhkan')
        ->assertDontSee('Sepatu Dihapus')
        ->set('status', 'nonaktif')
        ->assertSee('Kaos Nonaktif')
        ->assertDontSee('Jaket Tayang')
        ->set('status', 'dihapus')
        ->assertSee('Sepatu Dihapus')
        ->set('status', 'semua')
        ->set('storeId', $active->store_id)
        ->assertSee('Jaket Tayang')
        ->assertDontSee('Kaos Nonaktif')
        ->set('storeId', null)
        ->set('search', $active->store->owner->email)
        ->assertSee('Jaket Tayang')
        ->assertDontSee('Kaos Nonaktif');
});

it('bans a product so it leaves the catalog and the seller sees why', function () {
    $product = Product::factory()->create(['name' => 'Tas Replika']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin-products')
        ->call('startBan', $product->id)
        ->call('ban')
        ->assertHasErrors(['banReason' => 'required'])
        ->set('banReason', 'Barang replika merek terkenal.')
        ->call('ban')
        ->assertHasNoErrors()
        ->assertSet('banningId', null)
        ->assertSee('Diblokir');

    $product->refresh();

    expect($product->isBanned())->toBeTrue()
        ->and($product->isPurchasable())->toBeFalse()
        ->and(Product::purchasable()->whereKey($product)->exists())->toBeFalse();

    $this->actingAs(User::factory()->create())
        ->get(route('products.show', $product))
        ->assertNotFound();

    // Admin masih bisa memeriksa halamannya.
    $this->actingAs($this->admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('Pratinjau admin');

    $this->actingAs($product->store->owner)
        ->get(route('seller.products.index'))
        ->assertSee('Diblokir FitMate')
        ->assertSee('Barang replika merek terkenal.');

    Livewire::actingAs($this->superAdmin)
        ->test('pages::admin-products')
        ->call('unban', $product->id);

    expect($product->fresh()->isPurchasable())->toBeTrue();
});

it('deletes a product without breaking order history and can restore it', function () {
    $product = Product::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin-products')
        ->call('delete', $product->id)
        ->assertDispatched('notify');

    expect($product->fresh()->trashed())->toBeTrue()
        ->and(Product::purchasable()->whereKey($product)->exists())->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test('pages::admin-products')
        ->call('restore', $product->id);

    expect($product->fresh()->trashed())->toBeFalse();
});

it('filters the user list by ban status', function () {
    $banned = User::factory()->create(['name' => 'Pengguna Terblokir']);
    $banned->ban($this->admin, 'Spam ulasan dan penipuan.');
    User::factory()->create(['name' => 'Pengguna Baik']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin-users')
        ->set('status', 'diblokir')
        ->assertSee('Pengguna Terblokir')
        ->assertDontSee('Pengguna Baik');
});
