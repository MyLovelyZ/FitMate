<x-layouts.app title="Pesanan Dibuat">
    <div class="mx-auto flex max-w-md flex-col items-center gap-4 py-12 text-center">
        <x-ui.badge variant="success">Berhasil</x-ui.badge>

        <h1 class="text-2xl font-semibold">Pesanan berhasil dibuat</h1>
        <p class="text-sm text-gray-500">Selesaikan pembayaran sebelum batas waktu agar pesanan tidak dibatalkan.</p>

        <div class="flex gap-3">
            <x-ui.button :href="route('orders.index')">Lihat Pesanan</x-ui.button>
            <x-ui.button :href="route('products.index')" variant="secondary">Belanja Lagi</x-ui.button>
        </div>
    </div>
</x-layouts.app>
