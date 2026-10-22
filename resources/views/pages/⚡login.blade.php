<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Masuk')] class extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        $credentials = ['email' => $this->email, 'password' => $this->password];

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if (Auth::user()->isBanned()) {
            $reason = Auth::user()->ban_reason;
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akun ini diblokir oleh FitMate: '.$reason,
            ]);
        }

        session()->regenerate();

        $this->redirectIntended($this->defaultRedirectUrl(), navigate: true);
    }

    /**
     * Pembeli kembali ke beranda, staf langsung ke dashboard role masing-masing.
     */
    protected function defaultRedirectUrl(): string
    {
        $user = Auth::user();

        return $user->role === 'user'
            ? route('home')
            : route($user->dashboardRouteName());
    }

    /**
     * Menahan percobaan login beruntun dari kombinasi email + IP yang sama.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($this->throttleKey()).' detik.',
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
};
?>

<div>
    <h1 class="font-display text-[28px] font-normal leading-tight">Masuk</h1>
    <p class="mt-1.5 text-[13px] text-muted">Lanjutkan belanja dan lihat rekomendasi ukuranmu.</p>

    {{-- Dari middleware EnsureUserIsNotBanned saat sesi akun yang diblokir dikeluarkan. --}}
    @if (session('status'))
        <p class="mt-5 rounded-[2px] border border-sale/30 bg-sale/5 px-3.5 py-3 text-[12.5px] text-sale" role="alert">{{ session('status') }}</p>
    @endif

    <form wire:submit="login" class="mt-7 flex flex-col gap-4">
        <x-ui.input
            name="email"
            label="Email"
            type="email"
            wire:model="email"
            autocomplete="email"
            autofocus
        />

        <x-ui.input
            name="password"
            label="Kata Sandi"
            type="password"
            wire:model="password"
            autocomplete="current-password"
        />

        <label class="flex w-fit cursor-pointer items-center gap-2 text-[13px] text-muted">
            <input type="checkbox" wire:model="remember" class="size-4 accent-ink">
            Ingat saya
        </label>

        <x-ui.button target="login" class="mt-2">Masuk</x-ui.button>
    </form>

    <p class="mt-6 text-center text-[13px] text-muted">
        Belum punya akun?
        <a href="{{ route('register') }}" wire:navigate class="font-semibold text-ink underline-offset-4 hover:underline">Daftar</a>
    </p>
</div>
