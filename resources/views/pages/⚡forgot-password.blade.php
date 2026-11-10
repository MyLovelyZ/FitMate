<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Meminta link reset kata sandi. Pesannya sengaja sama untuk email terdaftar
 * maupun tidak, supaya halaman ini tidak bisa dipakai menebak akun.
 */
new #[Layout('layouts::auth')] #[Title('Lupa Kata Sandi')] class extends Component
{
    #[Validate('required|string|email|max:255', as: 'email')]
    public string $email = '';

    public bool $sent = false;

    public function sendResetLink(): void
    {
        $this->email = trim($this->email);

        $this->validate(messages: [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]);

        $status = Password::sendResetLink(['email' => strtolower(trim($this->email))]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'Link reset baru saja dikirim. Tunggu sebentar sebelum meminta lagi.',
            ]);
        }

        $this->sent = true;
    }
};
?>

<div>
    <h1 class="font-display text-[28px] font-normal leading-tight">Lupa Kata Sandi</h1>
    <p class="mt-1.5 text-[13px] text-muted">Masukkan email akunmu. Kami kirim link untuk membuat kata sandi baru.</p>

    @if ($sent)
        <p class="mt-6 rounded-[2px] border border-success/30 bg-success/12 px-3.5 py-3 text-[12.5px] text-success" role="status">
            Kalau email itu terdaftar, link reset kata sandi sudah dikirim. Cek kotak masuk (dan folder spam). Link berlaku 60 menit.
        </p>
    @endif

    <form wire:submit="sendResetLink" class="mt-7 flex flex-col gap-4">
        <x-ui.input name="email" label="Email" type="email" wire:model="email" autocomplete="email" autofocus />

        <x-ui.button target="sendResetLink" class="mt-2">Kirim Link Reset</x-ui.button>
    </form>

    <p class="mt-6 text-center text-[13px] text-muted">
        Ingat kata sandimu?
        <a href="{{ route('login') }}" wire:navigate class="font-semibold text-ink underline-offset-4 hover:underline">Masuk</a>
    </p>
</div>
