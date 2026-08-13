<x-layouts.app title="Checkout">
    <x-ui.page-header title="Checkout" />

    <form action="{{ route('checkout.index') }}" method="POST" class="grid gap-6 lg:grid-cols-3">
        @csrf

        <div class="flex flex-col gap-4 lg:col-span-2">
            <x-ui.card heading="Alamat Pengiriman">
                {{-- TODO(BE-070): pilih dari user_addresses, lalu salin ke kolom shipping_* di orders. --}}
                <x-ui.select name="user_address_id" placeholder="Pilih alamat" :options="[]" />
            </x-ui.card>

            <x-ui.card heading="Pengiriman per Toko">
                {{-- TODO(BE-071): satu pilihan kurir untuk tiap store_order. --}}
                <p class="text-sm text-gray-500">Belum ada barang di keranjang.</p>
            </x-ui.card>

            <x-ui.card heading="Metode Pembayaran">
                {{-- TODO(BE-072): daftar metode dari payment gateway yang dipilih (KEP-3). --}}
                <p class="text-sm text-gray-500">Menunggu keputusan payment gateway.</p>
            </x-ui.card>
        </div>

        <x-ui.card heading="Ringkasan" class="h-fit">
            <x-form.field name="coupon_code" label="Kode Kupon">
                <x-ui.input name="coupon_code" placeholder="FITMATE10" />
            </x-form.field>

            <dl class="mt-4 flex flex-col gap-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>Rp0</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Ongkir</dt><dd>Rp0</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Diskon</dt><dd>Rp0</dd></div>
                <div class="flex justify-between border-t border-gray-200 pt-2 font-semibold"><dt>Total</dt><dd>Rp0</dd></div>
            </dl>

            <x-slot:footer>
                <x-ui.button type="submit" class="w-full">Buat Pesanan</x-ui.button>
            </x-slot>
        </x-ui.card>
    </form>
</x-layouts.app>
