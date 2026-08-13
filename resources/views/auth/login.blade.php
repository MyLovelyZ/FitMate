<x-layouts.auth title="Masuk" heading="Masuk ke FitMate" subheading="Belum punya akun? Daftar dulu, gratis.">
    <form action="{{ route('login') }}" method="POST" class="flex flex-col gap-4">
        @csrf

        <x-form.field name="email" label="Email" required>
            <x-ui.input name="email" type="email" autocomplete="email" autofocus />
        </x-form.field>

        <x-form.field name="password" label="Kata Sandi" required>
            <x-ui.input name="password" type="password" autocomplete="current-password" />
        </x-form.field>

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-gray-600">
                <input type="checkbox" name="remember" class="rounded border-gray-300 text-brand-600">
                Ingat saya
            </label>

            <a href="{{ route('password.request') }}" class="text-brand-600 hover:underline">Lupa kata sandi?</a>
        </div>

        <x-ui.button type="submit" class="w-full">Masuk</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        Belum punya akun? <a href="{{ route('register') }}" class="font-medium text-brand-600 hover:underline">Daftar</a>
    </p>
</x-layouts.auth>
