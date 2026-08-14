<x-layouts.app title="Checkout">
    <x-ui.page-header title="Checkout" />

    <form action="{{ route('checkout.store') }}" method="POST" class="grid gap-6 lg:grid-cols-3">
        @csrf

        <div class="flex flex-col gap-4 lg:col-span-2">
            <x-ui.card heading="Alamat Pengiriman">
                @if ($addresses->isEmpty())
                    <div class="flex flex-col items-start gap-3">
                        <p class="text-sm text-gray-600">Kamu belum punya alamat. Tambahkan dulu sebelum checkout.</p>
                        <x-ui.button :href="route('profile.addresses')" size="sm">Tambah Alamat</x-ui.button>
                    </div>
                @else
                    <div class="flex flex-col gap-2">
                        @foreach ($addresses as $address)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 hover:border-brand-600">
                                <input
                                    type="radio"
                                    name="user_address_id"
                                    value="{{ $address->id }}"
                                    class="mt-1 text-brand-600"
                                    @checked(old('user_address_id', $addresses->firstWhere('is_default', true)?->id) == $address->id)
                                >

                                <span class="flex flex-col gap-0.5 text-sm">
                                    <span class="font-medium">
                                        {{ $address->label }} &middot; {{ $address->recipient_name }}
                                        @if ($address->is_default)
                                            <x-ui.badge variant="brand">Utama</x-ui.badge>
                                        @endif
                                    </span>

                                    <span class="text-gray-500">{{ $address->recipient_phone }}</span>
                                    <span class="text-gray-500">{{ $address->fullAddress() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card heading="Barang dan Pengiriman per Toko">
                <div class="flex flex-col divide-y divide-gray-100">
                    @foreach ($itemsByStore as $storeId => $items)
                        @php($store = $items->first()->variant->product->store)

                        <div class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0">
                            <p class="font-medium">{{ $store?->name }}</p>

                            @foreach ($items as $item)
                                <div class="flex justify-between text-sm text-gray-600">
                                    <span>{{ $item->variant->product->name }} &middot; {{ $item->variant->displayName() }} &times; {{ $item->quantity }}</span>
                                    <span>Rp{{ number_format($item->subtotal(), 0, ',', '.') }}</span>
                                </div>
                            @endforeach

                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Ongkir toko ini</span>
                                <span>Rp{{ number_format($shippingPerStore[$storeId] ?? 0, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    <x-form.field name="courier" label="Kurir" hint="Sementara tarif flat per toko; integrasi API kurir menunggu KEP-4.">
                        <x-ui.select name="courier" :options="$couriers" :selected="old('courier', array_key_first($couriers))" />
                    </x-form.field>
                </div>
            </x-ui.card>

            <x-ui.card heading="Metode Pembayaran">
                <div class="flex flex-col gap-2">
                    @foreach ($paymentMethods as $value => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3 text-sm hover:border-brand-600">
                            <input type="radio" name="payment_method" value="{{ $value }}" class="text-brand-600" @checked(old('payment_method', array_key_first($paymentMethods)) === $value)>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                <p class="mt-3 text-xs text-gray-500">
                    Gateway pembayaran otomatis menyusul setelah KEP-3 diputuskan. Untuk sekarang instruksi pembayaran
                    dikirim setelah pesanan dibuat.
                </p>
            </x-ui.card>

            <x-ui.card heading="Catatan">
                <x-ui.textarea name="note" rows="3" placeholder="Catatan untuk penjual, misalnya minta dibungkus rapi." :value="old('note')" />
            </x-ui.card>
        </div>

        <x-ui.card heading="Ringkasan" class="h-fit">
            <x-form.field name="coupon_code" label="Kode Kupon">
                <x-ui.input name="coupon_code" placeholder="FITMATE10" :value="old('coupon_code', request('coupon_code'))" />
            </x-form.field>

            @if ($appliedCoupon)
                <p class="mt-1 text-xs text-green-700">Kupon {{ $appliedCoupon->code }} siap dipakai.</p>
            @endif

            <dl class="mt-4 flex flex-col gap-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>Rp{{ number_format($subtotal, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Ongkir</dt><dd>Rp{{ number_format($shippingTotal, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Diskon</dt><dd>&minus;Rp{{ number_format($discount, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between border-t border-gray-200 pt-2 font-semibold"><dt>Total</dt><dd>Rp{{ number_format($grandTotal, 0, ',', '.') }}</dd></div>
            </dl>

            <x-slot:footer>
                <x-ui.button type="submit" class="w-full" :disabled="$addresses->isEmpty()">Buat Pesanan</x-ui.button>
            </x-slot>
        </x-ui.card>
    </form>
</x-layouts.app>
