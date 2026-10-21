<?php

/**
 * Pengajuan toko oleh pembeli (KEP-5) dan review admin: setujui, tolak,
 * tangguhkan, aktifkan lagi. Termasuk penyimpanan KTP & NIK yang privat.
 */

use App\Models\PayoutAccount;
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
 * Form "Buka Toko" yang sudah terisi lengkap dan valid.
 */
function filledStoreApplication(User $applicant): Testable
{
    return Livewire::actingAs($applicant)
        ->test('pages::store-apply')
        ->set('name', 'Toko Uji Coba')
        ->set('description', 'Kami menjual kaos katun premium dengan potongan yang pas untuk ukuran Asia.')
        ->set('phoneNumber', '081234567890')
        ->set('email', 'toko@fitmate.test')
        ->set('street', 'Jl. Merdeka No. 1')
        ->set('city', 'Bandung')
        ->set('province', 'Jawa Barat')
        ->set('postalCode', '40111')
        ->set('identityName', 'Zahir Pemilik')
        ->set('identityNumber', '3273010101010001')
        ->set('identityCard', UploadedFile::fake()->image('ktp.jpg', 800, 500))
        ->set('bankCode', 'bca')
        ->set('accountName', 'Zahir Pemilik')
        ->set('accountNumber', '1234567890')
        ->set('agreed', true);
}

it('asks guests to log in before applying', function () {
    $this->get(route('stores.create'))->assertRedirect(route('login'));
});

it('shows the application form to a buyer without a store', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('stores.create'))
        ->assertOk()
        ->assertSee('Buka Toko di FitMate')
        ->assertSee('Foto KTP')
        ->assertSee('Rekening Pencairan Dana');
});

it('submits an application as a pending store with private identity data', function () {
    $applicant = User::factory()->create();

    filledStoreApplication($applicant)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSee('Menunggu Review');

    $store = Store::sole();

    expect($store)
        ->user_id->toBe($applicant->id)
        ->status->toBe('pending')
        ->slug->toBe('toko-uji-coba')
        ->identity_number->toBe('3273010101010001')
        ->and($store->submitted_at)->not->toBeNull()
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

it('validates the identity and payout fields', function () {
    filledStoreApplication(User::factory()->create())
        ->set('identityNumber', '12345')
        ->set('identityCard', null)
        ->set('phoneNumber', '12345')
        ->set('bankCode', 'bank-palsu')
        ->set('agreed', false)
        ->call('submit')
        ->assertHasErrors([
            'identityNumber' => 'digits',
            'identityCard' => 'required',
            'phoneNumber' => 'regex',
            'bankCode' => 'in',
            'agreed' => 'accepted',
        ]);

    expect(Store::count())->toBe(0);
});

it('refuses a store name that another store already uses', function () {
    Store::factory()->create(['name' => 'Toko Uji Coba']);

    filledStoreApplication(User::factory()->create())
        ->call('submit')
        ->assertHasErrors(['name' => 'unique']);
});

it('does not let a pending applicant submit again', function () {
    $store = Store::factory()->pending()->create();

    Livewire::actingAs($store->owner)
        ->test('pages::store-apply')
        ->assertSee('Menunggu Review')
        ->call('submit')
        ->assertForbidden();
});

it('lets a rejected applicant fix the data and resubmit', function () {
    $store = Store::factory()->rejected('Foto KTP buram.')->withIdentityCard()->create();

    Livewire::actingAs($store->owner)
        ->test('pages::store-apply')
        ->assertSee('Foto KTP buram.')
        ->assertSet('name', $store->name)
        ->set('bankCode', 'bni')
        ->set('accountName', 'Pemilik Baru')
        ->set('accountNumber', '9876543210')
        ->set('agreed', true)
        ->call('submit')
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
        ->call('submit')
        ->assertForbidden();
});

it('lists pending applications for admins only', function () {
    $store = Store::factory()->pending()->create(['name' => 'Toko Menunggu']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.stores.index'))
        ->assertOk()
        ->assertSee('Toko Menunggu')
        ->assertSee(route('admin.stores.show', $store), false);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.stores.index'))
        ->assertRedirect('/');
});

it('approves an application: store goes live and the owner becomes a seller', function () {
    $admin = User::factory()->admin()->create();
    $store = Store::factory()->pending()->create();
    PayoutAccount::factory()->unverified()->for($store->owner)->create();

    Livewire::actingAs($admin)
        ->test('pages::admin-store-review', ['store' => $store])
        ->call('approve')
        ->assertDispatched('notify');

    $store->refresh();

    expect($store)
        ->status->toBe('active')
        ->reviewed_by->toBe($admin->id)
        ->and($store->canSell())->toBeTrue()
        ->and($store->owner->role)->toBe('seller')
        ->and($store->payoutAccount()->verified_at)->not->toBeNull();
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
