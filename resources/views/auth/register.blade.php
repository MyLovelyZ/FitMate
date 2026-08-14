<x-layouts.auth title="Daftar" heading="Buat Akun FitMate" subheading="Setelah daftar, isi ukuran badanmu untuk mendapat rekomendasi ukuran.">
    <form action="{{ route('register') }}" method="POST" class="flex flex-col gap-4">
        @csrf

        <x-form.field name="name" label="Nama Lengkap" required>
            <x-ui.input name="name" autocomplete="name" autofocus />
        </x-form.field>

        <x-form.field name="email" label="Email" required>
            <x-ui.input name="email" type="email" autocomplete="email" />
        </x-form.field>

        <x-form.field name="phone_number" label="Nomor HP">
            <x-ui.input name="phone_number" type="tel" autocomplete="tel" />
        </x-form.field>

        <x-form.field name="gender" label="Jenis Kelamin" hint="Dipakai untuk memilih tabel ukuran yang benar.">
            <x-ui.select name="gender" placeholder="Pilih..." :options="collect($genderOptions)->except('unisex')->all()" />
        </x-form.field>

        <x-form.field name="password" label="Kata Sandi" required hint="Minimal 8 karakter.">
            <x-ui.input name="password" type="password" autocomplete="new-password" />
        </x-form.field>

        <x-form.field name="password_confirmation" label="Ulangi Kata Sandi" required>
            <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" />
        </x-form.field>

        <x-ui.button type="submit" class="w-full">Daftar</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        Sudah punya akun? <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:underline">Masuk</a>
    </p>
</x-layouts.auth>
