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
        session()->regenerate();

        $this->redirectIntended(route('home'), navigate: true);
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
    <h1 class="text-2xl font-semibold tracking-tight text-navy-second">Masuk</h1>
    <p class="mt-1 text-sm text-abu-second">Lanjutkan belanja dan lihat rekomendasi ukuranmu.</p>

    <form wire:submit="login" class="mt-6 space-y-4">
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

        <label class="flex items-center gap-2 text-sm text-abu-second">
            <input
                type="checkbox"
                wire:model="remember"
                class="size-4 rounded border-abu-second/60 text-brand-600 focus:ring-brand-200"
            >
            Ingat saya
        </label>

        <x-ui.button>
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login">Memproses…</span>
        </x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-abu-second">
        Belum punya akun?
        <a href="{{ route('register') }}" wire:navigate class="font-medium text-brand-600 hover:underline">
            Daftar di sini
        </a>
    </p>
</div>
