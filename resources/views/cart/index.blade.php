<x-layouts.app title="Keranjang">
    <x-ui.page-header title="Keranjang" subtitle="Barang dikelompokkan per toko, ongkir dihitung per toko." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-4 lg:col-span-2">
            {{-- TODO(BE-064): @foreach ($cart->items->groupBy('variant.product.store_id') as $storeItems). --}}
            <x-ui.empty-state title="Keranjang masih kosong" description="Cari produk yang ukurannya pas untukmu.">
                <x-ui.button :href="route('products.index')" size="sm">Mulai Belanja</x-ui.button>
            </x-ui.empty-state>
        </div>

        <x-ui.card heading="Ringkasan" class="h-fit">
            <dl class="flex flex-col gap-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>Rp0</dd></div>
                <div class="flex justify-between font-semibold"><dt>Total</dt><dd>Rp0</dd></div>
            </dl>

            <x-slot:footer>
                <x-ui.button :href="route('checkout.index')" class="w-full">Checkout</x-ui.button>
            </x-slot>
        </x-ui.card>
    </div>
</x-layouts.app>
