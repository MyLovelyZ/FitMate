<x-layouts.app title="Detail Pesanan">
    <x-ui.page-header title="Pesanan #—" subtitle="Dibuat pada —">
        <x-slot:actions>
            <x-ui.badge variant="warning">Menunggu Pembayaran</x-ui.badge>
        </x-slot>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-4 lg:col-span-2">
            {{-- TODO(BE-074): satu kartu per store_order, status jalan sendiri-sendiri per toko. --}}
            <x-ui.card heading="Barang">
                <p class="text-sm text-gray-500">Data order_items disalin saat checkout, bukan diambil ulang dari produk.</p>
            </x-ui.card>

            <x-ui.card heading="Pengiriman">
                {{-- TODO(BE-076): nomor resi dan status pengiriman. --}}
                <p class="text-sm text-gray-500">Belum ada informasi pengiriman.</p>
            </x-ui.card>
        </div>

        <x-ui.card heading="Pembayaran" class="h-fit">
            <p class="text-sm text-gray-500">Rincian pembayaran.</p>
        </x-ui.card>
    </div>
</x-layouts.app>
