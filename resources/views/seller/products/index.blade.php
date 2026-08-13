<x-layouts.dashboard title="Produk" area="seller">
    <x-ui.page-header title="Produk" subtitle="Kelola produk, varian, dan stok.">
        <x-slot:actions>
            <x-ui.button size="sm">Tambah Produk</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    {{-- TODO(BE-052): tabel produk milik toko ini saja. --}}
    <x-ui.empty-state title="Belum ada produk" description="Tambahkan produk pertamamu, lengkap dengan ukuran aslinya." />
</x-layouts.dashboard>
