<?php

/**
 * Halaman profil (`profile`): dipakai semua role. Data diri, foto, dan kata sandi
 * untuk semua; ukuran badan dan alamat hanya untuk pembeli.
 */

use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserBodyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sends guests to the login page', function () {
    $this->get(route('profile'))->assertRedirect(route('login'));
});

it('opens the profile page for every role', function (?string $state) {
    $factory = User::factory();
    $user = ($state ? $factory->{$state}() : $factory)->create();

    $this->actingAs($user)
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('Profil Saya')
        ->assertSee($user->email)
        ->assertSeeInOrder(['Beranda', 'Profil Saya']);
})->with([null, 'seller', 'customerService', 'admin', 'superAdmin']);

it('shows body size and address tabs only to buyers', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile'))
        ->assertSee('Ukuran Badan')
        ->assertSee('Alamat');

    $this->actingAs(User::factory()->seller()->create())
        ->get(route('profile'))
        ->assertDontSee('Ukuran Badan');
});

it('falls back to the first tab when the url asks for a tab the role cannot see', function () {
    Livewire::withQueryParams(['tab' => 'ukuran'])
        ->actingAs(User::factory()->seller()->create())
        ->test('pages::profile')
        ->assertSet('tab', 'data');
});

it('updates the personal details', function () {
    $user = User::factory()->create(['gender' => null]);

    Livewire::actingAs($user)
        ->test('pages::profile')
        ->set('name', 'Nama Baru')
        ->set('email', 'baru@fitmate.test')
        ->set('phoneNumber', '081234567890')
        ->set('gender', 'female')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($user->fresh())
        ->name->toBe('Nama Baru')
        ->email->toBe('baru@fitmate.test')
        ->phone_number->toBe('081234567890')
        ->gender->toBe('female');
});

it('refuses an email that belongs to someone else', function () {
    User::factory()->create(['email' => 'dipakai@fitmate.test']);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile')
        ->set('email', 'dipakai@fitmate.test')
        ->call('saveProfile')
        ->assertHasErrors(['email' => 'unique']);
});

it('uploads a new profile picture and removes the old one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('avatars/lama.jpg', 'foto lama');

    $user = User::factory()->create(['profile_picture' => 'avatars/lama.jpg']);

    Livewire::actingAs($user)
        ->test('pages::profile')
        ->set('photo', UploadedFile::fake()->image('wajah.jpg', 400, 400))
        ->call('saveProfile')
        ->assertHasNoErrors();

    $newPath = $user->fresh()->profile_picture;

    expect($newPath)->toStartWith('avatars/')->not->toBe('avatars/lama.jpg');
    Storage::disk('public')->assertExists($newPath);
    Storage::disk('public')->assertMissing('avatars/lama.jpg');
});

it('rejects a profile picture that is not an image', function () {
    Storage::fake('public');

    Livewire::actingAs(User::factory()->create())
        ->test('pages::profile')
        ->set('photo', UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'))
        ->assertHasErrors('photo');
});

it('removes the profile picture', function () {
    Storage::fake('public');
    Storage::disk('public')->put('avatars/wajah.jpg', 'foto');

    $user = User::factory()->create(['profile_picture' => 'avatars/wajah.jpg']);

    Livewire::actingAs($user)
        ->test('pages::profile')
        ->call('removePhoto');

    expect($user->fresh()->profile_picture)->toBeNull();
    Storage::disk('public')->assertMissing('avatars/wajah.jpg');
});

it('changes the password only with the right current password', function () {
    $user = User::factory()->create(['password' => Hash::make('rahasia-lama')]);

    Livewire::actingAs($user)
        ->test('pages::profile')
        ->set('currentPassword', 'salah-tebak')
        ->set('password', 'rahasia-baru')
        ->set('password_confirmation', 'rahasia-baru')
        ->call('updatePassword')
        ->assertHasErrors(['currentPassword' => 'current_password']);

    Livewire::actingAs($user)
        ->test('pages::profile')
        ->set('currentPassword', 'rahasia-lama')
        ->set('password', 'rahasia-baru')
        ->set('password_confirmation', 'rahasia-baru')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSet('currentPassword', '')
        ->assertSet('password', '');

    expect(Hash::check('rahasia-baru', $user->fresh()->password))->toBeTrue();
});

it('saves the buyer body measurements as the default body profile', function () {
    $buyer = User::factory()->create(['gender' => 'male']);

    Livewire::actingAs($buyer)
        ->test('pages::profile')
        ->call('selectTab', 'ukuran')
        ->set('measurements.height', '172')
        ->set('measurements.chest', '96.5')
        ->set('measurements.waist', '')
        ->call('saveMeasurements')
        ->assertHasNoErrors();

    $profile = UserBodyProfile::where('user_id', $buyer->id)->where('is_default', true)->sole();

    expect((float) $profile->height)->toBe(172.0)
        ->and((float) $profile->chest)->toBe(96.5)
        ->and($profile->waist)->toBeNull()
        ->and($profile->gender)->toBe('male');
});

it('rejects body measurements outside a sensible range', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::profile')
        ->set('measurements.height', '900')
        ->call('saveMeasurements')
        ->assertHasErrors(['measurements.height' => 'between']);
});

it('saves the buyer default address', function () {
    $buyer = User::factory()->create();

    Livewire::actingAs($buyer)
        ->test('pages::profile')
        ->call('selectTab', 'alamat')
        ->set('address.recipient_name', 'Zahir')
        ->set('address.recipient_phone', '081200000000')
        ->set('address.street', 'Jl. Merdeka No. 1')
        ->set('address.city', 'Bandung')
        ->set('address.state', 'Jawa Barat')
        ->set('address.postal_code', '40111')
        ->call('saveAddress')
        ->assertHasNoErrors();

    $address = UserAddress::where('user_id', $buyer->id)->where('is_default', true)->sole();

    expect($address)
        ->city->toBe('Bandung')
        ->country->toBe('Indonesia');
});

it('requires the address fields', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::profile')
        ->set('address.street', '')
        ->set('address.city', '')
        ->call('saveAddress')
        ->assertHasErrors(['address.street' => 'required', 'address.city' => 'required']);
});

it('saves the theme preference for every role and switches the open page', function (?string $state) {
    $factory = User::factory();
    $user = ($state ? $factory->{$state}() : $factory)->create();

    Livewire::actingAs($user)
        ->test('pages::profile')
        ->call('selectTab', 'tampilan')
        ->assertSet('tab', 'tampilan')
        ->assertSet('theme', 'system')
        ->set('theme', 'dark')
        ->assertHasNoErrors()
        ->assertDispatched('theme-changed', theme: 'dark')
        ->assertDispatched('notify');

    expect($user->fresh()->theme)->toBe('dark');
})->with([null, 'seller', 'admin']);

it('rejects a theme that is not offered', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::profile')
        ->set('theme', 'neon')
        ->assertHasErrors(['theme' => 'in'])
        ->assertNotDispatched('theme-changed');

    expect($user->fresh()->theme)->toBe('system');
});

it('renders the saved theme on the html element', function () {
    $this->get(route('login'))->assertSee('data-theme="system"', false);

    $this->actingAs(User::factory()->create(['theme' => 'dark']))
        ->get(route('home'))
        ->assertSee('data-theme="dark"', false);
});

it('forbids staff from saving buyer-only data', function () {
    Livewire::actingAs(User::factory()->seller()->create())
        ->test('pages::profile')
        ->call('saveMeasurements')
        ->assertForbidden();
});

it('lists account links for the role in the navbar menu', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertSee(route('profile'), false)
        ->assertSee(route('user.orders'), false);

    $this->actingAs(User::factory()->seller()->create())
        ->get(route('home'))
        ->assertSee(route('seller.dashboard'), false)
        ->assertSee(route('profile'), false)
        ->assertDontSee(route('user.cart'), false);
});
