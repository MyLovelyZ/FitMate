<x-layouts.dashboard title="Ringkasan Toko" area="seller">
    <x-ui.page-header :title="$store->name" subtitle="Kondisi tokomu hari ini.">
        <x-slot:actions>
            <x-ui.badge :variant="$store->isActive() ? 'success' : 'warning'">{{ $store->status->label() }}</x-ui.badge>
        </x-slot>
    </x-ui.page-header>

    @unless ($store->isActive())
        <x-ui.alert variant="warning" class="mb-6" :dismissible="false">
            Tokomu belum aktif, jadi produknya belum tampil di katalog. Kamu tetap bisa menyiapkan produk sambil menunggu
            verifikasi admin.
        </x-ui.alert>
    @endunless

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.card>
            <p class="text-sm text-gray-500">Pesanan Baru</p>
            <p class="mt-1 text-2xl font-semibold">{{ $newOrderCount }}</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500">Perlu Dikirim</p>
            <p class="mt-1 text-2xl font-semibold">{{ $toShipCount }}</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500">Produk Aktif</p>
            <p class="mt-1 text-2xl font-semibold">{{ $activeProductCount }}</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500">Rating Toko</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format((float) $store->rating_average, 1) }}</p>
            <p class="text-xs text-gray-400">{{ $store->rating_count }} ulasan</p>
        </x-ui.card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card heading="Pesanan Terbaru">
            @if ($recentOrders->isEmpty())
                <p class="text-sm text-gray-500">Belum ada pesanan masuk.</p>
            @else
                <div class="flex flex-col divide-y divide-gray-100">
                    @foreach ($recentOrders as $storeOrder)
                        <a href="{{ route('seller.orders.show', $storeOrder) }}" class="flex items-center justify-between gap-3 py-3 text-sm first:pt-0 last:pb-0 hover:text-brand-600">
                            <span>
                                {{ $storeOrder->order->order_number }}
                                <span class="text-xs text-gray-500">&middot; {{ $storeOrder->items->count() }} barang</span>
                            </span>

                            <x-ui.badge>{{ $storeOrder->status->label() }}</x-ui.badge>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card heading="Stok Menipis">
            @if ($lowStockVariants->isEmpty())
                <p class="text-sm text-gray-500">Semua varian stoknya masih aman.</p>
            @else
                <div class="flex flex-col divide-y divide-gray-100">
                    @foreach ($lowStockVariants as $product)
                        @foreach ($product->variants as $variant)
                            <div class="flex items-center justify-between gap-3 py-2 text-sm first:pt-0 last:pb-0">
                                <span>
                                    {{ $product->name }}
                                    <span class="text-xs text-gray-500">&middot; {{ $variant->displayName() }}</span>
                                </span>

                                <x-ui.badge :variant="$variant->stock < 1 ? 'danger' : 'warning'">Sisa {{ $variant->stock }}</x-ui.badge>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.dashboard>
