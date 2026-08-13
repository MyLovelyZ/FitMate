<x-layouts.dashboard title="Ringkasan Admin" area="admin">
    <x-ui.page-header title="Ringkasan Admin" subtitle="Kondisi platform secara keseluruhan." />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.card>
            <p class="text-sm text-gray-500">Toko Menunggu Verifikasi</p>
            <p class="mt-1 text-2xl font-semibold">0</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500">Produk Menunggu Review</p>
            <p class="mt-1 text-2xl font-semibold">0</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500">Pesanan Hari Ini</p>
            <p class="mt-1 text-2xl font-semibold">0</p>
        </x-ui.card>

        {{-- TODO(BE-082): ini metrik yang menutup lingkaran umpan balik ukuran. --}}
        <x-ui.card>
            <p class="text-sm text-gray-500">Keluhan Ukuran</p>
            <p class="mt-1 text-2xl font-semibold">0</p>
        </x-ui.card>
    </div>
</x-layouts.dashboard>
