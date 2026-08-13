<x-layouts.app title="Pesanan Saya">
    <x-ui.page-header title="Pesanan Saya" subtitle="Riwayat seluruh pesananmu." />

    {{-- TODO(BE-078): @foreach ($orders as $order) beserta status tiap store_order. --}}
    <x-ui.empty-state title="Belum ada pesanan" description="Pesanan yang kamu buat akan muncul di sini." />
</x-layouts.app>
