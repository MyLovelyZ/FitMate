<x-layouts.app title="Profil Saya">
    <x-ui.page-header title="Profil Saya" />

    <div class="grid gap-6 lg:grid-cols-4">
        <aside class="lg:col-span-1">
            <nav class="flex gap-1 overflow-x-auto lg:flex-col">
                <x-ui.nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">Data Akun</x-ui.nav-link>
                <x-ui.nav-link :href="route('profile.body')" :active="request()->routeIs('profile.body')">Ukuran Badan</x-ui.nav-link>
                <x-ui.nav-link :href="route('profile.addresses')" :active="request()->routeIs('profile.addresses')">Alamat</x-ui.nav-link>
            </nav>
        </aside>

        <div class="lg:col-span-3">
            {{-- TODO(BE-043): form update profil, termasuk unggah foto. --}}
            <x-ui.card heading="Data Akun">
                <form action="{{ route('profile.edit') }}" method="POST" class="flex max-w-md flex-col gap-4">
                    @csrf
                    @method('PATCH')

                    <x-form.field name="name" label="Nama Lengkap" required>
                        <x-ui.input name="name" :value="auth()->user()?->name" />
                    </x-form.field>

                    <x-form.field name="email" label="Email" required>
                        <x-ui.input name="email" type="email" :value="auth()->user()?->email" />
                    </x-form.field>

                    <x-form.field name="phone_number" label="Nomor HP">
                        <x-ui.input name="phone_number" type="tel" />
                    </x-form.field>

                    <x-ui.button type="submit" class="self-start">Simpan</x-ui.button>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
