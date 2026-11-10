<?php

/**
 * Lupa kata sandi: minta link lewat email, lalu buat kata sandi baru dari link itu.
 */

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('links the login page to the forgot password page', function () {
    $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
    $this->get(route('password.request'))->assertOk()->assertSee('Lupa Kata Sandi');
});

it('emails a reset link in Indonesian to a registered account', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'zahir@fitmate.test']);

    Livewire::test('pages::forgot-password')
        ->set('email', ' Zahir@FitMate.test ')
        ->call('sendResetLink')
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSee('link reset kata sandi sudah dikirim');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        return $mail->subject === 'Reset Kata Sandi FitMate'
            && str_contains($mail->actionUrl, route('password.reset', ['token' => $notification->token]));
    });
});

it('gives the same answer for an unknown email', function () {
    Notification::fake();

    Livewire::test('pages::forgot-password')
        ->set('email', 'tidak-ada@fitmate.test')
        ->call('sendResetLink')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    Notification::assertNothingSent();
});

it('validates the email field', function () {
    Livewire::test('pages::forgot-password')
        ->set('email', 'bukan-email')
        ->call('sendResetLink')
        ->assertHasErrors('email');
});

it('sets a new password from a valid reset link', function () {
    $user = User::factory()->create(['email' => 'zahir@fitmate.test']);
    $token = Password::createToken($user);

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->assertOk()
        ->assertSee('Buat Kata Sandi Baru');

    Livewire::withQueryParams(['email' => $user->email])
        ->test('pages::reset-password', ['token' => $token])
        ->assertSet('email', $user->email)
        ->set('password', 'rahasiaBaru123')
        ->set('password_confirmation', 'rahasiaBaru123')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('rahasiaBaru123', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid token, a short password, and a mismatched confirmation', function () {
    $user = User::factory()->create();

    Livewire::test('pages::reset-password', ['token' => 'token-palsu'])
        ->set('email', $user->email)
        ->set('password', 'rahasiaBaru123')
        ->set('password_confirmation', 'rahasiaBaru123')
        ->call('resetPassword')
        ->assertHasErrors('email');

    Livewire::test('pages::reset-password', ['token' => Password::createToken($user)])
        ->set('email', $user->email)
        ->set('password', 'pendek')
        ->set('password_confirmation', 'beda')
        ->call('resetPassword')
        ->assertHasErrors('password');

    expect(Hash::check('rahasiaBaru123', $user->fresh()->password))->toBeFalse();
});

it('keeps logged-in users away from the reset pages', function () {
    $this->actingAs(User::factory()->create())->get(route('password.request'))->assertRedirect();
});
