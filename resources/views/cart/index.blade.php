<x-layouts.app title="Keranjang">
    <x-ui.page-header title="Keranjang" subtitle="Barang dikelompokkan per toko, ongkir dihitung per toko." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-4 lg:col-span-2">
            @if ($cart->items->isEmpty())
                <x-ui.empty-state title="Keranjang masih kosong" description="Cari produk yang ukurannya pas untukmu.">
                    <x-ui.button :href="route('products.index')" size="sm">Mulai Belanja</x-ui.button>
                </x-ui.empty-state>
            @else
                @foreach ($itemsByStore as $items)
                    @php($store = $items->first()->variant->product->store)

                    <x-ui.card :heading="$store?->name">
                        <div class="flex flex-col divide-y divide-gray-100">
                            @foreach ($items as $item)
                                @php($variant = $item->variant)

                                <div class="flex gap-4 py-4 first:pt-0 last:pb-0">
                                    <a href="{{ route('products.show', $variant->product) }}" class="size-20 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                                        @if ($variant->product->primaryImage)
                                            <img src="{{ $variant->product->primaryImage->url() }}" alt="" class="h-full w-full object-cover">
                                        @endif
                                    </a>

                                    <div class="flex flex-1 flex-col gap-1">
                                        <a href="{{ route('products.show', $variant->product) }}" class="text-sm font-medium hover:text-brand-600">
                                            {{ $variant->product->name }}
                                        </a>

                                        <p class="text-xs text-gray-500">{{ $variant->displayName() }}</p>
                                        <p class="text-sm font-semibold">Rp{{ number_format((float) $variant->price, 0, ',', '.') }}</p>

                                        @if ($item->exceedsStock())
                                            <p class="text-xs text-red-600">Stok tersisa {{ $variant->stock }}, kurangi jumlahnya sebelum checkout.</p>
                                        @endif
                                    </div>

                                    <div class="flex flex-col items-end gap-2">
                                        <form action="{{ route('cart.update', $item) }}" method="POST" class="flex items-center gap-1">
                                            @csrf
                                            @method('PATCH')

                                            <input
                                                type="number"
                                                name="quantity"
                                                value="{{ $item->quantity }}"
                                                min="1"
                                                max="{{ max(1, $variant->stock) }}"
                                                class="w-16 rounded-lg border-0 px-2 py-1 text-center text-sm ring-1 ring-gray-300 ring-inset"
                                            >

                                            <x-ui.button type="submit" size="sm" variant="secondary">Ubah</x-ui.button>
                                        </form>

                                        <form action="{{ route('cart.destroy', $item) }}" method="POST">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="text-xs text-red-600 hover:underline">Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endforeach
            @endif
        </div>

        <x-ui.card heading="Ringkasan" class="h-fit">
            <dl class="flex flex-col gap-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Subtotal ({{ $cart->totalQuantity() }} barang)</dt>
                    <dd>Rp{{ number_format($subtotal, 0, ',', '.') }}</dd>
                </div>

                <div class="flex justify-between text-xs text-gray-500">
                    <dt>Ongkir</dt>
                    <dd>Dihitung saat checkout</dd>
                </div>

                <div class="flex justify-between border-t border-gray-200 pt-2 font-semibold">
                    <dt>Total sementara</dt>
                    <dd>Rp{{ number_format($subtotal, 0, ',', '.') }}</dd>
                </div>
            </dl>

            <x-slot:footer>
                @if ($cart->items->isEmpty())
                    <x-ui.button :href="route('products.index')" variant="secondary" class="w-full">Mulai Belanja</x-ui.button>
                @else
                    <x-ui.button :href="route('checkout.index')" class="w-full">Checkout</x-ui.button>
                @endif
            </x-slot>
        </x-ui.card>
    </div>
</x-layouts.app>
