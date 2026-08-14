<x-layouts.app title="Pesanan Dibuat">
    <div class="mx-auto flex max-w-md flex-col items-center gap-4 py-12 text-center">
        <x-ui.badge variant="success">Berhasil</x-ui.badge>

        <h1 class="text-2xl font-semibold">Pesanan berhasil dibuat</h1>

        <p class="text-sm text-gray-500">
            Nomor pesananmu <span class="font-medium text-gray-900">{{ $order->order_number }}</span>.
            Selesaikan pembayaran sebelum batas waktu agar pesanan tidak dibatalkan.
        </p>

        <x-ui.card class="w-full text-left">
            <dl class="flex flex-col gap-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Total tagihan</dt><dd class="font-semibold">Rp{{ number_format((float) $order->grand_total, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Jumlah toko</dt><dd>{{ $order->storeOrders->count() }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd>{{ $order->status->label() }}</dd></div>
            </dl>
        </x-ui.card>

        <div class="flex gap-3">
            <x-ui.button :href="route('orders.show', $order)">Lihat Pesanan</x-ui.button>
            <x-ui.button :href="route('products.index')" variant="secondary">Belanja Lagi</x-ui.button>
        </div>
    </div>
</x-layouts.app>
