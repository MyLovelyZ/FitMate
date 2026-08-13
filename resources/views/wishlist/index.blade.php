<x-layouts.app title="Wishlist">
    <x-ui.page-header title="Wishlist" subtitle="Produk yang kamu simpan." />

    {{-- TODO(BE-065): @foreach ($wishlists as $wishlist). --}}
    <x-ui.empty-state title="Wishlist masih kosong" description="Simpan produk yang kamu incar agar mudah ditemukan lagi.">
        <x-ui.button :href="route('products.index')" size="sm">Lihat Katalog</x-ui.button>
    </x-ui.empty-state>
</x-layouts.app>
