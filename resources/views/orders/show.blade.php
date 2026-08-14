<x-layouts.app :title="'Pesanan '.$order->order_number">
    <x-ui.page-header
        :title="'Pesanan '.$order->order_number"
        :subtitle="'Dibuat pada '.($order->placed_at?->translatedFormat('d F Y H:i') ?? '—')"
    >
        <x-slot:actions>
            <x-ui.badge :variant="$order->status->value === 'completed' ? 'success' : ($order->status->value === 'cancelled' ? 'danger' : 'warning')">
                {{ $order->status->label() }}
            </x-ui.badge>

            @can('cancel', $order)
                <form action="{{ route('orders.cancel', $order) }}" method="POST">
                    @csrf
                    <x-ui.button type="submit" variant="danger" size="sm">Batalkan</x-ui.button>
                </form>
            @endcan
        </x-slot>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-4 lg:col-span-2">
            @foreach ($order->storeOrders as $storeOrder)
                <x-ui.card :heading="$storeOrder->store?->name">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <x-ui.badge>{{ $storeOrder->status->label() }}</x-ui.badge>
                        <span class="text-xs text-gray-500">{{ $storeOrder->store_order_number }}</span>
                    </div>

                    <div class="flex flex-col divide-y divide-gray-100">
                        @foreach ($storeOrder->items as $item)
                            <div class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0">
                                <div class="flex justify-between gap-3 text-sm">
                                    <span class="font-medium">{{ $item->product_name }}</span>
                                    <span>Rp{{ number_format((float) $item->subtotal, 0, ',', '.') }}</span>
                                </div>

                                <p class="text-xs text-gray-500">
                                    {{ collect([$item->size_label, $item->color_name])->filter()->implode(' · ') ?: 'Standar' }}
                                    &times; {{ $item->quantity }}
                                </p>

                                @if ($item->recommended_size_label)
                                    <p class="text-xs {{ $item->followedRecommendation() ? 'text-green-700' : 'text-amber-700' }}">
                                        FitMate menyarankan ukuran {{ $item->recommended_size_label }}
                                        @unless ($item->followedRecommendation())
                                            &mdash; kamu memilih {{ $item->size_label }}
                                        @endunless
                                    </p>
                                @endif

                                @if (in_array($storeOrder->status->value, ['delivered', 'completed'], true) && $item->review === null)
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-xs text-brand-600">Tulis ulasan</summary>

                                        <form action="{{ route('reviews.store', $item) }}" method="POST" class="mt-2 flex flex-col gap-2">
                                            @csrf

                                            <x-ui.select name="rating" :options="[5 => '5 bintang', 4 => '4 bintang', 3 => '3 bintang', 2 => '2 bintang', 1 => '1 bintang']" />
                                            <x-ui.select name="fit_feedback" placeholder="Bagaimana ukurannya?" :options="App\Enums\FitFeedback::options()" />
                                            <x-ui.textarea name="comment" rows="2" placeholder="Ceritakan pengalamanmu." />

                                            <x-ui.button type="submit" size="sm" class="self-start">Kirim Ulasan</x-ui.button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <x-slot:footer>
                        <div class="flex flex-col gap-1 text-sm">
                            <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span>Rp{{ number_format((float) $storeOrder->subtotal, 0, ',', '.') }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Ongkir</span><span>Rp{{ number_format((float) $storeOrder->shipping_cost, 0, ',', '.') }}</span></div>

                            @if ($storeOrder->shipment)
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Pengiriman</span>
                                    <span>
                                        {{ strtoupper($storeOrder->shipment->courier) }}
                                        {{ $storeOrder->shipment->tracking_number ? '· '.$storeOrder->shipment->tracking_number : '· belum ada resi' }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </x-slot>
                </x-ui.card>
            @endforeach

            <x-ui.card heading="Alamat Pengiriman">
                <p class="text-sm font-medium">{{ $order->recipient_name }} &middot; {{ $order->recipient_phone }}</p>
                <p class="mt-1 text-sm text-gray-600">{{ $order->shippingAddress() }}</p>
            </x-ui.card>
        </div>

        <x-ui.card heading="Pembayaran" class="h-fit">
            <dl class="flex flex-col gap-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>Rp{{ number_format((float) $order->subtotal, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Ongkir</dt><dd>Rp{{ number_format((float) $order->shipping_total, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Diskon</dt><dd>&minus;Rp{{ number_format((float) $order->discount_total, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between border-t border-gray-200 pt-2 font-semibold"><dt>Total</dt><dd>Rp{{ number_format((float) $order->grand_total, 0, ',', '.') }}</dd></div>
            </dl>

            @if ($payment)
                <div class="mt-4 flex flex-col gap-1 text-sm">
                    <p class="text-gray-500">Metode: {{ $payment->method->label() }}</p>
                    <p class="text-gray-500">Status: {{ $payment->status->label() }}</p>

                    @if ($payment->expires_at)
                        <p class="text-gray-500">Batas bayar: {{ $payment->expires_at->translatedFormat('d M Y H:i') }}</p>
                    @endif
                </div>
            @endif

            @if ($order->coupon)
                <p class="mt-3 text-xs text-gray-500">Kupon terpakai: {{ $order->coupon->code }}</p>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
