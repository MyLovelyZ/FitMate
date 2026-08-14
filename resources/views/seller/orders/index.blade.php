<x-layouts.dashboard title="Pesanan Masuk" area="seller">
    <x-ui.page-header title="Pesanan Masuk" subtitle="Hanya pesanan untuk tokomu." />

    <form action="{{ route('seller.orders.index') }}" method="GET" class="mb-4 flex max-w-xs items-center gap-2">
        <x-ui.select name="status" placeholder="Semua status" :options="$statusOptions" :selected="request('status')" x-on:change="$el.form.submit()" />
    </form>

    @if ($storeOrders->isEmpty())
        <x-ui.empty-state title="Belum ada pesanan" description="Pesanan yang masuk ke tokomu akan muncul di sini." />
    @else
        <x-ui.card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="py-2 pr-4">Nomor</th>
                            <th class="py-2 pr-4">Penerima</th>
                            <th class="py-2 pr-4">Barang</th>
                            <th class="py-2 pr-4">Total</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        @foreach ($storeOrders as $storeOrder)
                            <tr>
                                <td class="py-3 pr-4">
                                    <p class="font-medium">{{ $storeOrder->store_order_number }}</p>
                                    <p class="text-xs text-gray-500">{{ $storeOrder->order->placed_at?->translatedFormat('d M Y H:i') }}</p>
                                </td>
                                <td class="py-3 pr-4 text-gray-600">{{ $storeOrder->order->recipient_name }}</td>
                                <td class="py-3 pr-4 text-gray-500">{{ $storeOrder->items->count() }}</td>
                                <td class="py-3 pr-4">Rp{{ number_format((float) $storeOrder->total, 0, ',', '.') }}</td>
                                <td class="py-3 pr-4"><x-ui.badge>{{ $storeOrder->status->label() }}</x-ui.badge></td>
                                <td class="py-3">
                                    <a href="{{ route('seller.orders.show', $storeOrder) }}" class="text-brand-600 hover:underline">Kelola</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div class="mt-6">{{ $storeOrders->links() }}</div>
    @endif
</x-layouts.dashboard>
