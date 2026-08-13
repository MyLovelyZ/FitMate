<x-layouts.dashboard title="Pesanan Masuk" area="seller">
    <x-ui.page-header title="Pesanan Masuk" subtitle="Hanya pesanan untuk tokomu." />

    {{-- TODO(BE-077): daftar store_orders milik toko ini. Jangan sampai bocor ke toko lain. --}}
    <x-ui.empty-state title="Belum ada pesanan" />
</x-layouts.dashboard>
