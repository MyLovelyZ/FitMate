<x-layouts.auth title="Atur Ulang Kata Sandi" heading="Atur Ulang Kata Sandi">
    <form action="{{ route('password.update') }}" method="POST" class="flex flex-col gap-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token ?? '' }}">

        <x-form.field name="email" label="Email" required>
            <x-ui.input name="email" type="email" :value="request('email')" />
        </x-form.field>

        <x-form.field name="password" label="Kata Sandi Baru" required>
            <x-ui.input name="password" type="password" autocomplete="new-password" />
        </x-form.field>

        <x-form.field name="password_confirmation" label="Ulangi Kata Sandi Baru" required>
            <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" />
        </x-form.field>

        <x-ui.button type="submit" class="w-full">Simpan Kata Sandi</x-ui.button>
    </form>
</x-layouts.auth>
