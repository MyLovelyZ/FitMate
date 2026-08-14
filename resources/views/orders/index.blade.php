<x-layouts.app title="Pesanan Saya">
    <x-ui.page-header title="Pesanan Saya" subtitle="Riwayat seluruh pesananmu." />

    @if ($orders->isEmpty())
        <x-ui.empty-state title="Belum ada pesanan" description="Pesanan yang kamu buat akan muncul di sini.">
            <x-ui.button :href="route('products.index')" size="sm">Mulai Belanja</x-ui.button>
        </x-ui.empty-state>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($orders as $order)
                <x-ui.card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex flex-col gap-1">
                            <a href="{{ route('orders.show', $order) }}" class="font-medium hover:text-brand-600">
                                {{ $order->order_number }}
                            </a>

                            <p class="text-xs text-gray-500">
                                {{ $order->placed_at?->translatedFormat('d M Y H:i') ?? '—' }}
                                &middot; {{ $order->storeOrders->count() }} toko
                                &middot; {{ $order->storeOrders->sum(fn ($storeOrder) => $storeOrder->items->count()) }} barang
                            </p>
                        </div>

                        <div class="flex flex-col items-end gap-1">
                            <x-ui.badge :variant="$order->status->value === 'completed' ? 'success' : ($order->status->value === 'cancelled' ? 'danger' : 'warning')">
                                {{ $order->status->label() }}
                            </x-ui.badge>

                            <span class="text-sm font-semibold">Rp{{ number_format((float) $order->grand_total, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($order->storeOrders as $storeOrder)
                            <x-ui.badge>{{ $storeOrder->store?->name }}: {{ $storeOrder->status->label() }}</x-ui.badge>
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</x-layouts.app>
