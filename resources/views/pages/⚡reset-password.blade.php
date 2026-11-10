<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Membuat kata sandi baru dari link di email (route `password.reset`, nama yang
 * dipakai notifikasi bawaan Laravel).
 */
new #[Layout('layouts::auth')] #[Title('Buat Kata Sandi Baru')] class extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        $status = Password::reset(
            [
                'email' => strtolower(trim($this->email)),
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => $status === Password::INVALID_USER
                    ? 'Email tidak cocok dengan link reset ini.'
                    : 'Link reset sudah tidak berlaku. Minta link baru.',
            ]);
        }

        session()->flash('success', 'Kata sandi berhasil diganti. Silakan masuk dengan kata sandi baru.');

        $this->redirectRoute('login', navigate: true);
    }
};
?>

<div>
    <h1 class="font-display text-[28px] font-normal leading-tight">Buat Kata Sandi Baru</h1>
    <p class="mt-1.5 text-[13px] text-muted">Minimal 8 karakter. Setelah ini, masuk dengan kata sandi baru.</p>

    <form wire:submit="resetPassword" class="mt-7 flex flex-col gap-4">
        <x-ui.input name="email" label="Email" type="email" wire:model="email" value="{{ $email }}" autocomplete="email" />
        <x-ui.input name="password" label="Kata Sandi Baru" type="password" wire:model="password" autocomplete="new-password" autofocus />
        <x-ui.input name="password_confirmation" label="Ulangi Kata Sandi" type="password" wire:model="password_confirmation" autocomplete="new-password" />

        <x-ui.button target="resetPassword" class="mt-2">Simpan Kata Sandi</x-ui.button>
    </form>

    <p class="mt-6 text-center text-[13px] text-muted">
        <a href="{{ route('password.request') }}" wire:navigate class="font-semibold text-ink underline-offset-4 hover:underline">Minta link baru</a>
    </p>
</div>
