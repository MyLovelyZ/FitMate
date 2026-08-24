<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Daftar')] class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate();

        $user = User::create($validated);

        Auth::login($user);
        session()->regenerate();

        $this->redirect(route('home'), navigate: true);
    }
};
?>

<div>
    <h1 class="text-2xl font-semibold tracking-tight text-navy-second">Buat Akun</h1>
    <p class="mt-1 text-sm text-abu-second">Simpan ukuran badanmu sekali, pakai di semua produk.</p>

    <form wire:submit="register" class="mt-6 space-y-4">
        <x-ui.input
            name="name"
            label="Nama Lengkap"
            wire:model="name"
            autocomplete="name"
            autofocus
        />

        <x-ui.input
            name="email"
            label="Email"
            type="email"
            wire:model="email"
            autocomplete="email"
        />

        <x-ui.input
            name="password"
            label="Kata Sandi"
            type="password"
            wire:model="password"
            autocomplete="new-password"
            hint="Minimal 8 karakter."
        />

        <x-ui.input
            name="password_confirmation"
            label="Ulangi Kata Sandi"
            type="password"
            wire:model="password_confirmation"
            autocomplete="new-password"
        />

        <x-ui.button>
            <span wire:loading.remove wire:target="register">Daftar</span>
            <span wire:loading wire:target="register">Memproses…</span>
        </x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-abu-second">
        Sudah punya akun?
        <a href="{{ route('login') }}" wire:navigate class="font-medium text-brand-600 hover:underline">
            Masuk di sini
        </a>
    </p>
</div>
