<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('logs in a user with the right credentials', function () {
    $user = User::factory()->create(['password' => Hash::make('rahasia123')]);

    Livewire::test('pages::login')
        ->set('email', $user->email)
        ->set('password', 'rahasia123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect(auth()->id())->toBe($user->id);
});

it('rejects a wrong password without saying which field was wrong', function () {
    $user = User::factory()->create(['password' => Hash::make('rahasia123')]);

    Livewire::test('pages::login')
        ->set('email', $user->email)
        ->set('password', 'salah-total')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('requires both login fields', function () {
    Livewire::test('pages::login')
        ->call('login')
        ->assertHasErrors(['email' => 'required', 'password' => 'required']);
});

it('throttles login after five failed attempts', function () {
    $user = User::factory()->create(['password' => Hash::make('rahasia123')]);

    $component = Livewire::test('pages::login')
        ->set('email', $user->email)
        ->set('password', 'salah-total');

    foreach (range(1, 5) as $attempt) {
        $component->call('login');
    }

    $component->set('password', 'rahasia123')->call('login');

    expect(auth()->check())->toBeFalse();
});

it('registers a new user and signs them in', function () {
    Livewire::test('pages::register')
        ->set('name', 'Zahir')
        ->set('email', 'zahir@fitmate.test')
        ->set('password', 'rahasia123')
        ->set('password_confirmation', 'rahasia123')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::where('email', 'zahir@fitmate.test')->firstOrFail();

    expect(auth()->id())->toBe($user->id)
        ->and($user->role)->toBe('user')
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

it('refuses an email that is already registered', function () {
    User::factory()->create(['email' => 'zahir@fitmate.test']);

    Livewire::test('pages::register')
        ->set('name', 'Zahir')
        ->set('email', 'zahir@fitmate.test')
        ->set('password', 'rahasia123')
        ->set('password_confirmation', 'rahasia123')
        ->call('register')
        ->assertHasErrors(['email' => 'unique']);
});

it('refuses a password confirmation that does not match', function () {
    Livewire::test('pages::register')
        ->set('name', 'Zahir')
        ->set('email', 'zahir@fitmate.test')
        ->set('password', 'rahasia123')
        ->set('password_confirmation', 'beda-sendiri')
        ->call('register')
        ->assertHasErrors(['password' => 'confirmed']);

    expect(User::count())->toBe(0);
});

it('logs the user out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    expect(auth()->check())->toBeFalse();
});

it('keeps signed-in users away from the auth pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('login'))->assertRedirect();
    $this->actingAs($user)->get(route('register'))->assertRedirect();
});
