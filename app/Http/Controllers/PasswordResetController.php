<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = PasswordBroker::sendResetLink($request->only('email'));

        // Jawabannya sengaja selalu sama, supaya halaman ini tidak bisa dipakai
        // menebak email mana yang terdaftar.
        return back()->with(
            'success',
            $status === PasswordBroker::RESET_LINK_SENT
                ? 'Kalau emailnya terdaftar, tautan pengaturan ulang sudah kami kirim.'
                : 'Kalau emailnya terdaftar, tautan pengaturan ulang sudah kami kirim.',
        );
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $status = PasswordBroker::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Tautan pengaturan ulang sudah tidak berlaku. Minta tautan baru.']);
        }

        return redirect()
            ->route('login')
            ->with('success', 'Kata sandimu sudah diperbarui. Silakan masuk.');
    }
}
