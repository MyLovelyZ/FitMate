<x-layouts.dashboard :title="'Pesanan '.$storeOrder->store_order_number" area="seller">
    <x-ui.page-header
        :title="$storeOrder->store_order_number"
        :subtitle="'Bagian dari pesanan '.$storeOrder->order->order_number"
    >
        <x-slot:actions>
            <x-ui.badge>{{ $storeOrder->status->label() }}</x-ui.badge>
        </x-slot>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-4 lg:col-span-2">
            <x-ui.card heading="Barang">
                <div class="flex flex-col divide-y divide-gray-100">
                    @foreach ($storeOrder->items as $item)
                        <div class="flex justify-between gap-3 py-3 text-sm first:pt-0 last:pb-0">
                            <div>
                                <p class="font-medium">{{ $item->product_name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ collect([$item->size_label, $item->color_name])->filter()->implode(' · ') ?: 'Standar' }}
                                    &times; {{ $item->quantity }}
                                </p>
                            </div>

                            <span>Rp{{ number_format((float) $item->subtotal, 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>

                <x-slot:footer>
                    <div class="flex flex-col gap-1 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span>Rp{{ number_format((float) $storeOrder->subtotal, 0, ',', '.') }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Ongkir</span><span>Rp{{ number_format((float) $storeOrder->shipping_cost, 0, ',', '.') }}</span></div>
                        <div class="flex justify-between font-semibold"><span>Total</span><span>Rp{{ number_format((float) $storeOrder->total, 0, ',', '.') }}</span></div>
                    </div>
                </x-slot>
            </x-ui.card>

            <x-ui.card heading="Alamat Pengiriman">
                <p class="text-sm font-medium">{{ $storeOrder->order->recipient_name }} &middot; {{ $storeOrder->order->recipient_phone }}</p>
                <p class="mt-1 text-sm text-gray-600">{{ $storeOrder->order->shippingAddress() }}</p>
            </x-ui.card>
        </div>

        <div class="flex flex-col gap-4">
            <x-ui.card heading="Ubah Status">
                @if ($nextStatuses === [])
                    <p class="text-sm text-gray-500">Status pesanan ini sudah final.</p>
                @else
                    <form action="{{ route('seller.orders.status', $storeOrder) }}" method="POST" class="flex flex-col gap-3">
                        @csrf
                        @method('PATCH')

                        <x-ui.select
                            name="status"
                            :options="collect($nextStatuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()"
                        />

                        <x-ui.button type="submit" size="sm">Simpan Status</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card heading="Pengiriman">
                @if ($storeOrder->shipment?->tracking_number)
                    <div class="flex flex-col gap-1 text-sm">
                        <p><span class="text-gray-500">Kurir:</span> {{ strtoupper($storeOrder->shipment->courier) }}</p>
                        <p><span class="text-gray-500">Resi:</span> {{ $storeOrder->shipment->tracking_number }}</p>
                        <p><span class="text-gray-500">Status:</span> {{ $storeOrder->shipment->status->label() }}</p>
                    </div>
                @else
                    <form action="{{ route('seller.orders.ship', $storeOrder) }}" method="POST" class="flex flex-col gap-3">
                        @csrf

                        <x-form.field name="courier" label="Kurir" required>
                            <x-ui.select name="courier" :options="$couriers" :selected="$storeOrder->shipment?->courier" />
                        </x-form.field>

                        <x-form.field name="service" label="Layanan">
                            <x-ui.input name="service" placeholder="reg" :value="$storeOrder->shipment?->service" />
                        </x-form.field>

                        <x-form.field name="tracking_number" label="Nomor Resi" required>
                            <x-ui.input name="tracking_number" />
                        </x-form.field>

                        <x-ui.button type="submit" size="sm">Simpan Resi &amp; Kirim</x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.dashboard>
