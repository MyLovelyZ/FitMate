<x-layouts.app title="Alamat Saya">
    <x-ui.page-header title="Alamat Saya" subtitle="Alamat utama dipakai otomatis saat checkout.">
        <x-slot:actions>
            <x-ui.button size="sm" x-on:click="$dispatch('open-modal', 'address-form')">Tambah Alamat</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    {{-- TODO(BE-044): daftar user_addresses, tandai yang is_default. --}}
    <x-ui.empty-state title="Belum ada alamat" description="Tambahkan alamat agar bisa checkout." />

    <x-ui.modal name="address-form" title="Tambah Alamat">
        <form action="{{ route('profile.addresses') }}" method="POST" class="flex flex-col gap-4">
            @csrf

            <x-form.field name="label" label="Label" hint="Contoh: Rumah, Kantor.">
                <x-ui.input name="label" />
            </x-form.field>

            <x-form.field name="recipient_name" label="Nama Penerima" required>
                <x-ui.input name="recipient_name" />
            </x-form.field>

            <x-form.field name="address_line" label="Alamat Lengkap" required>
                <x-ui.textarea name="address_line" />
            </x-form.field>

            <x-ui.button type="submit">Simpan Alamat</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.app>
