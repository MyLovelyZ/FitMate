<x-layouts.dashboard title="Kategori dan Brand" area="admin">
    <x-ui.page-header title="Kategori dan Brand" subtitle="Kategori menentukan size_type produk di bawahnya.">
        <x-slot:actions>
            <x-ui.button size="sm">Tambah Kategori</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    {{-- TODO(BE-057): CRUD kategori bertingkat dan brand. --}}
    <x-ui.empty-state title="Belum ada kategori" />
</x-layouts.dashboard>
