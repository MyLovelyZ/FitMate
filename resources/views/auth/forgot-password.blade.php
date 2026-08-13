<x-layouts.auth title="Lupa Kata Sandi" heading="Lupa Kata Sandi" subheading="Masukkan emailmu, kami kirim tautan untuk mengatur ulang.">
    <form action="{{ route('password.email') }}" method="POST" class="flex flex-col gap-4">
        @csrf

        <x-form.field name="email" label="Email" required>
            <x-ui.input name="email" type="email" autofocus />
        </x-form.field>

        <x-ui.button type="submit" class="w-full">Kirim Tautan</x-ui.button>
    </form>
</x-layouts.auth>
