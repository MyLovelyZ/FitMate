<x-layouts.dashboard title="Profil Toko" area="seller">
    <x-ui.page-header title="Profil Toko" />

    {{-- TODO(BE-051): form profil toko, logo, banner, dan alamat asal pengiriman. --}}
    <x-ui.card heading="Informasi Toko">
        <form action="#" method="POST" class="flex max-w-md flex-col gap-4">
            @csrf

            <x-form.field name="name" label="Nama Toko" required>
                <x-ui.input name="name" />
            </x-form.field>

            <x-form.field name="description" label="Deskripsi">
                <x-ui.textarea name="description" />
            </x-form.field>

            <x-ui.button type="submit" class="self-start">Simpan</x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.dashboard>
