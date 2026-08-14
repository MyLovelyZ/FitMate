<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showRegister(): View
    {
        return view('auth.register', [
            'genderOptions' => Gender::options(),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            ...$validated,
            'role' => UserRole::User,
            'status' => UserStatus::Active,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('profile.body')
            ->with('success', 'Akunmu sudah jadi. Isi ukuran badanmu supaya rekomendasi ukurannya akurat.');
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi yang kamu masukkan salah.',
            ]);
        }

        if ($request->user()->status === UserStatus::Inactive) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akun ini sedang dinonaktifkan. Hubungi customer service.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->homeFor($request->user()));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Setiap peran punya halaman awal yang berbeda.
     */
    private function homeFor(User $user): string
    {
        return match (true) {
            $user->isAdmin() => route('admin.dashboard'),
            $user->isSeller() => route('seller.dashboard'),
            default => route('home'),
        };
    }
}
