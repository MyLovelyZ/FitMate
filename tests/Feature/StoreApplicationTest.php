<?php

/**
 * Pengajuan toko oleh pembeli (KEP-5): halaman perkenalan, formulir bertahap
 * (alamat → KTP → rekening), review admin (setujui, tolak, tangguhkan, aktifkan
 * lagi), lalu profil toko yang baru diisi setelah disetujui. Termasuk
 * penyimpanan KTP & NIK yang privat.
 */

use App\Models\PayoutAccount;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(Store::IDENTITY_DISK);
    Storage::fake('public');
});

/**
 * Formulir "Buka Toko" yang sudah diisi sampai langkah terakhir (rekening),
 * tinggal dikirim.
 */
function filledStoreApplication(User $applicant): Testable
{
    return Livewire::actingAs($applicant)
        ->test('pages::store-apply')
        ->call('start')
        ->set('street', 'Jl. Merdeka No. 1')
        ->set('city', 'Bandung')
        ->set('province', 'Jawa Barat')
        ->set('postalCode', '40111')
        ->call('next')
        ->set('identityName', 'Zahir Pemilik')
        ->set('identityNumber', '3273010101010001')
        ->set('identityCard', UploadedFile::fake()->image('ktp.jpg', 800, 500))
        ->call('next')
        ->set('bankCode', 'bca')
        ->set('accountName', 'Zahir Pemilik')
        ->set('accountNumber', '1234567890')
        ->set('agreed', true);
}

/**
 * Pengajuan yang sudah disetujui admin tapi profil tokonya belum diisi.
 */
function approvedStoreWithoutProfile(): Store
{
    $store = Store::factory()->pending()->withoutProfile()->create();
    $store->approve(User::factory()->admin()->create());

    return $store->fresh();
}

it('introduces the seller program to guests and sends them to log in to start', function () {
    $this->get(route('stores.create'))
        ->assertOk()
        ->assertSee('Jual koleksimu ke pembeli yang sudah tahu ukurannya.')
        ->assertSee('Kenapa berjualan di FitMate')
        ->assertSee('Masuk &amp; Mulai Daftar', false)
        ->assertDontSee('Langkah 1 dari 3');

    Livewire::test('pages::store-apply')
        ->call('start')
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('stores.create', ['mulai' => 1]));
});

it('opens with the introduction before the form for a buyer without a store', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('stores.create'))
        ->assertOk()
        ->assertSee('Mulai Daftar')
        ->assertSee('Cara kerjanya')
        ->assertDontSee('Langkah 1 dari 3');

    Livewire::actingAs(User::factory()->create())
        ->test('pages::store-apply')
        ->assertSet('step', 0)
        ->call('start')
        ->assertSet('step', 1)
        ->assertSee('Langkah 1 dari 3')
        ->assertSee('Alamat Asal Pengiriman')
        ->assertDontSee('Nama Toko');
});

it('jumps straight to the first step after logging in from the introduction', function () {
    Livewire::withQueryParams(['mulai' => 1])
        ->actingAs(User::factory()->create())
        ->test('pages::store-apply')
        ->assertSet('step', 1);
});

it('validates each step before moving to the next one', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::store-apply')
        ->call('start')
        ->call('next')
        ->assertHasErrors(['street' => 'required', 'postalCode' => 'required'])
        ->assertHasNoErrors('identityNumber')
        ->assertSet('step', 1)
        ->set('street', 'Jl. Merdeka No. 1')
        ->set('city', 'Bandung')
        ->set('province', 'Jawa Barat')
        ->set('postalCode', '40111')
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSee('Langkah 2 dari 3')
        ->set('identityNumber', '12345')
        ->call('next')
        ->assertHasErrors(['identityNumber' => 'digits', 'identityCard' => 'required'])
        ->assertSet('step', 2);

    expect(Store::count())->toBe(0);
});

it('validates the payout step before sending', function () {
    filledStoreApplication(User::factory()->create())
        ->set('bankCode', 'bank-palsu')
        ->set('agreed', false)
        ->call('next')
        ->assertHasErrors(['bankCode' => 'in', 'agreed' => 'accepted'])
        ->assertSet('step', 3);

    expect(Store::count())->toBe(0);
});

it('goes back to an earlier step without losing what was filled in', function () {
    filledStoreApplication(User::factory()->create())
        ->call('back')
        ->assertSet('step', 2)
        ->call('goToStep', 1)
        ->assertSet('step', 1)
        ->assertSet('street', 'Jl. Merdeka No. 1')
        ->call('goToStep', 3)
        ->assertSet('step', 1);
});

it('sends the buyer back to the step that is still wrong', function () {
    filledStoreApplication(User::factory()->create())
        ->set('city', '')
        ->call('submit')
        ->assertHasErrors(['city' => 'required'])
        ->assertSet('step', 1);

    expect(Store::count())->toBe(0);
});

it('submits an application as a pending store with private identity data and no profile yet', function () {
    $applicant = User::factory()->create();

    filledStoreApplication($applicant)
        ->call('next')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSee('Pengajuanmu sedang ditinjau')
        ->assertSee('Jl. Merdeka No. 1');

    $store = Store::sole();

    expect($store)
        ->user_id->toBe($applicant->id)
        ->status->toBe('pending')
        ->name->toBeNull()
        ->slug->toBeNull()
        ->city->toBe('Bandung')
        ->identity_number->toBe('3273010101010001')
        ->and($store->submitted_at)->not->toBeNull()
        ->and($store->hasProfile())->toBeFalse()
        ->and($applicant->fresh()->role)->toBe('user');

    // KTP di disk privat, NIK terenkripsi di database.
    Storage::disk(Store::IDENTITY_DISK)->assertExists($store->identity_card_path);
    Storage::disk('public')->assertMissing($store->identity_card_path);
    expect(DB::table('stores')->value('identity_number'))->not->toBe('3273010101010001');

    $account = PayoutAccount::sole();
    expect($account)
        ->user_id->toBe($applicant->id)
        ->bank_code->toBe('bca')
        ->verified_at->toBeNull();
});

it('does not let a pending applicant submit again', function () {
    $store = Store::factory()->pending()->withoutProfile()->create();

    Livewire::actingAs($store->owner)
        ->test('pages::store-apply')
        ->assertSee('Menunggu Review')
        ->call('submit')
        ->assertForbidden();
});

it('lets a rejected applicant fix the data and resubmit', function () {
    $store = Store::factory()->rejected('Foto KTP buram.')->withoutProfile()->withIdentityCard()->create();

    Livewire::actingAs($store->owner)
        ->test('pages::store-apply')
        ->assertSee('Foto KTP buram.')
        ->assertSet('step', 1)
        ->assertSet('street', $store->street)
        ->call('next')
        ->call('next')
        ->assertHasNoErrors()
        ->set('bankCode', 'bni')
        ->set('accountName', 'Pemilik Baru')
        ->set('accountNumber', '9876543210')
        ->set('agreed', true)
        ->assertSee('Kirim Ulang Pengajuan')
        ->call('next')
        ->assertHasNoErrors();

    expect($store->fresh())
        ->status->toBe('pending')
        ->review_note->toBeNull()
        ->identity_card_path->toBe('identity-cards/ktp.jpg');
});

it('keeps staff accounts from applying', function () {
    $staff = User::factory()->customerService()->create();

    $this->actingAs($staff)
        ->get(route('stores.create'))
        ->assertSee('Akun ini tidak bisa mengajukan toko');

    Livewire::actingAs($staff)
        ->test('pages::store-apply')
        ->call('start')
        ->assertForbidden();

    Livewire::actingAs($staff)
        ->test('pages::store-apply')
        ->call('submit')
        ->assertForbidden();
});

it('lists pending applications for admins only', function () {
    $store = Store::factory()->pending()->create(['name' => 'Toko Menunggu']);
    Store::factory()->pending()->withoutProfile()->create(['identity_name' => 'Siti Pemohon']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.stores.index'))
        ->assertOk()
        ->assertSee('Toko Menunggu')
        ->assertSee('Calon toko Siti Pemohon')
        ->assertSee(route('admin.stores.show', $store), false);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.stores.index'))
        ->assertRedirect('/');
});

it('approves an application: the owner becomes a seller who still has to fill in the store profile', function () {
    $admin = User::factory()->admin()->create();
    $store = Store::factory()->pending()->withoutProfile()->create();
    PayoutAccount::factory()->unverified()->for($store->owner)->create();

    $this->actingAs($admin)
        ->get(route('admin.stores.show', $store))
        ->assertOk()
        ->assertSee('Profil toko diisi setelah disetujui');

    Livewire::actingAs($admin)
        ->test('pages::admin-store-review', ['store' => $store])
        ->call('approve')
        ->assertDispatched('notify');

    $store->refresh();

    expect($store)
        ->status->toBe('active')
        ->reviewed_by->toBe($admin->id)
        ->and($store->needsProfile())->toBeTrue()
        ->and($store->canSell())->toBeFalse()
        ->and($store->owner->role)->toBe('seller')
        ->and($store->owner->accountLinks())->toHaveKey('Lengkapi Profil Toko')
        ->and($store->payoutAccount()->verified_at)->not->toBeNull();
});

it('asks the approved seller for the store profile before the store goes live', function () {
    $store = approvedStoreWithoutProfile();
    $product = Product::factory()->for($store)->create();

    expect(Product::purchasable()->whereKey($product)->exists())->toBeFalse();

    $this->actingAs($store->owner)
        ->get(route('seller.dashboard'))
        ->assertSee('Lengkapi Profil');

    Livewire::actingAs($store->owner)
        ->test('pages::store-apply')
        ->assertSee('Lengkapi Profil Toko')
        ->set('name', 'Toko Uji Coba')
        ->set('description', 'Kami menjual kaos katun premium dengan potongan yang pas untuk ukuran Asia.')
        ->set('phoneNumber', '081234567890')
        ->set('email', 'toko@fitmate.test')
        ->set('logo', UploadedFile::fake()->image('logo.png', 300, 300))
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSee('Tokomu sudah aktif dan bisa berjualan.');

    $store->refresh();

    expect($store)
        ->name->toBe('Toko Uji Coba')
        ->slug->toBe('toko-uji-coba')
        ->email->toBe('toko@fitmate.test')
        ->and($store->canSell())->toBeTrue()
        ->and(Product::purchasable()->whereKey($product)->exists())->toBeTrue()
        ->and($store->owner->fresh()->accountLinks())->not->toHaveKey('Lengkapi Profil Toko');

    Storage::disk('public')->assertExists($store->logo);
});

it('validates the store profile and refuses a name another store already uses', function () {
    Store::factory()->create(['name' => 'Toko Uji Coba']);
    $store = approvedStoreWithoutProfile();

    Livewire::actingAs($store->owner)
        ->test('pages::store-apply')
        ->set('name', 'Toko Uji Coba')
        ->set('description', 'Terlalu pendek.')
        ->set('phoneNumber', '12345')
        ->call('saveProfile')
        ->assertHasErrors(['name' => 'unique', 'description' => 'min', 'phoneNumber' => 'regex']);

    expect($store->fresh()->hasProfile())->toBeFalse();
});

it('does not let anyone fill in a store profile before approval', function () {
    $store = Store::factory()->pending()->withoutProfile()->create();

    Livewire::actingAs($store->owner)
        ->test('pages::store-apply')
        ->set('name', 'Toko Diam-Diam')
        ->call('saveProfile')
        ->assertForbidden();

    expect($store->fresh()->name)->toBeNull();
});

it('rejects an application only with a reason', function () {
    $admin = User::factory()->admin()->create();
    $store = Store::factory()->pending()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin-store-review', ['store' => $store])
        ->call('reject')
        ->assertHasErrors(['note' => 'required'])
        ->set('note', 'Nama rekening berbeda dengan nama di KTP.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($store->fresh())
        ->status->toBe('rejected')
        ->review_note->toBe('Nama rekening berbeda dengan nama di KTP.')
        ->and($store->owner->fresh()->role)->toBe('user');
});

it('suspends and reactivates an active store', function () {
    $admin = User::factory()->admin()->create();
    $store = Store::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test('pages::admin-store-review', ['store' => $store])
        ->set('note', 'Banyak komplain barang palsu.')
        ->call('suspend');

    expect($store->fresh()->status)->toBe('suspended');

    $component->call('reactivate');

    expect($store->fresh())
        ->status->toBe('active')
        ->review_note->toBeNull();
});

it('serves the identity card only to admins', function () {
    $store = Store::factory()->pending()->withIdentityCard('identity-cards/ktp-uji.jpg')->create();
    Storage::disk(Store::IDENTITY_DISK)->put('identity-cards/ktp-uji.jpg', UploadedFile::fake()->image('ktp.jpg')->getContent());

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.stores.identity-card', $store))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->actingAs($store->owner)
        ->get(route('admin.stores.identity-card', $store))
        ->assertRedirect('/');
});

it('shows the identity card and payout account on the review page', function () {
    $store = Store::factory()->pending()->withIdentityCard()->create(['identity_name' => 'Nama KTP']);
    PayoutAccount::factory()->for($store->owner)->create(['account_name' => 'Nama Lain', 'account_number' => '5550001111']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.stores.show', $store))
        ->assertOk()
        ->assertSee(route('admin.stores.identity-card', $store), false)
        ->assertSee('5550001111')
        ->assertSee('Nama pemilik rekening berbeda dengan nama di KTP')
        ->assertDontSee($store->identity_number);
});

it('offers the right store link in the buyer account menu', function () {
    $buyer = User::factory()->create();

    expect($buyer->accountLinks())->toHaveKey('Buka Toko');

    Store::factory()->pending()->for($buyer, 'owner')->create();

    expect($buyer->fresh()->accountLinks())->toHaveKey('Status Pengajuan Toko');
});
