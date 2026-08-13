<x-layouts.dashboard title="Ringkasan Toko" area="seller">
    <x-ui.page-header title="Ringkasan Toko" subtitle="Kondisi tokomu hari ini." />

    {{-- TODO(BE-077): angka asli, dan pastikan hanya data toko milik seller ini. --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.card>
            <p class="text-sm text-gray-500">Pesanan Baru</p>
            <p class="mt-1 text-2xl font-semibold">0</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500">Perlu Dikirim</p>
            <p class="mt-1 text-2xl font-semibold">0</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500">Produk Aktif</p>
            <p class="mt-1 text-2xl font-semibold">0</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500">Rating Toko</p>
            <p class="mt-1 text-2xl font-semibold">0.0</p>
        </x-ui.card>
    </div>
</x-layouts.dashboard>
