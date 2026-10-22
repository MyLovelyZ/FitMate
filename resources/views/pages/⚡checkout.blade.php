<?php

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Checkout')] class extends Component
{
    /**
     * Field alamat kirim: kolom `user_addresses` => label. Disalin ke kolom
     * `shipping_*` di `orders` saat pesanan dibuat.
     *
     * @var array<string, string>
     */
    public const ADDRESS_FIELDS = [
        'recipient_name' => 'Nama Penerima',
        'recipient_phone' => 'No. Telepon',
        'street' => 'Alamat Lengkap',
        'city' => 'Kota / Kabupaten',
        'state' => 'Provinsi',
        'postal_code' => 'Kode Pos',
    ];

    /** @var array<string, string> */
    public array $address = [];

    /** Simpan alamat ini sebagai alamat utama; hanya ditawarkan kalau belum punya. */
    public bool $saveAddress = true;

    public string $shippingOption = 'jne-reg';

    public string $paymentMethod = 'virtual_account';

    public string $vaBank = 'bca';

    public string $note = '';

    public function mount(): void
    {
        if ($this->items->isEmpty()) {
            $this->redirectRoute('user.cart', navigate: true);

            return;
        }

        $user = Auth::user();
        $default = $user->defaultAddress;

        $this->address = [
            'recipient_name' => $default->recipient_name ?? $user->name,
            'recipient_phone' => $default->recipient_phone ?? (string) $user->phone_number,
            'street' => $default->street ?? '',
            'city' => $default->city ?? '',
            'state' => $default->state ?? '',
            'postal_code' => $default->postal_code ?? '',
        ];
    }

    /**
     * @return Collection<int, CartItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return CartItem::query()
            ->whereHas('cart', fn ($query) => $query->whereBelongsTo(Auth::user()))
            ->with([
                'productVariant.product.store',
                'productVariant.product.primaryImage',
                'productVariant.size',
                'productVariant.color',
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * Satu paket per toko — tiap paket dikirim dan diongkiri sendiri.
     *
     * @return \Illuminate\Support\Collection<int, Collection<int, CartItem>>
     */
    #[Computed]
    public function packages(): \Illuminate\Support\Collection
    {
        return $this->items->groupBy(fn (CartItem $item): int => $item->productVariant->product->store_id);
    }

    /**
     * Barang yang tidak bisa dibeli (habis, stok kurang, tokonya berhenti jualan).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function problems(): array
    {
        return $this->items
            ->map(fn (CartItem $item): ?string => $item->checkoutProblem(Auth::user()))
            ->filter()
            ->values()
            ->all();
    }

    #[Computed]
    public function hasDefaultAddress(): bool
    {
        return Auth::user()->defaultAddress()->exists();
    }

    #[Computed]
    public function subtotal(): float
    {
        return $this->items->sum(fn (CartItem $item): float => $item->lineTotal());
    }

    #[Computed]
    public function shippingTotal(): float
    {
        return (Shipment::OPTIONS[$this->shippingOption]['cost'] ?? 0) * $this->packages->count();
    }

    #[Computed]
    public function total(): float
    {
        return $this->subtotal + $this->shippingTotal;
    }

    public function placeOrder(): void
    {
        $this->validate();

        $user = Auth::user();

        $order = Order::placeFromCart(
            buyer: $user,
            address: $this->address,
            shippingOption: $this->shippingOption,
            paymentMethod: $this->paymentMethod,
            vaBank: $this->paymentMethod === 'virtual_account' ? $this->vaBank : null,
            note: $this->note,
        );

        if ($this->saveAddress && ! $this->hasDefaultAddress) {
            $user->addresses()->create([
                ...$this->address,
                'label' => 'Rumah',
                'country' => 'Indonesia',
                'is_default' => true,
            ]);
        }

        $this->dispatch('cart-updated', count: 0);

        $this->redirectRoute('user.orders.show', $order, navigate: true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'address.recipient_name' => ['required', 'string', 'max:255'],
            'address.recipient_phone' => ['required', 'string', 'max:32'],
            'address.street' => ['required', 'string', 'max:500'],
            'address.city' => ['required', 'string', 'max:100'],
            'address.state' => ['required', 'string', 'max:100'],
            'address.postal_code' => ['required', 'string', 'max:10'],
            'shippingOption' => ['required', Rule::in(array_keys(Shipment::OPTIONS))],
            'paymentMethod' => ['required', Rule::in(Payment::CHECKOUT_METHODS)],
            'vaBank' => ['required_if:paymentMethod,virtual_account', Rule::in(array_keys(Payment::VA_BANKS))],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            ...collect(self::ADDRESS_FIELDS)->mapWithKeys(fn (string $label, string $field): array => [
                "address.{$field}" => Str::lower($label),
            ])->all(),
            'shippingOption' => 'pengiriman',
            'paymentMethod' => 'metode pembayaran',
            'vaBank' => 'bank',
            'note' => 'catatan',
        ];
    }
};
?>

<div class="mx-auto max-w-[1120px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(20px,4vw,32px)]">
    <x-ui.breadcrumbs :items="['Beranda' => route('home'), 'Keranjang' => route('user.cart'), 'Checkout' => null]" />

    <div class="mt-[clamp(24px,4vw,32px)] grid grid-cols-1 gap-[clamp(28px,5vw,55px)] lg:grid-cols-[minmax(0,1fr)_340px]">
        {{-- Kolom form --}}
        <section class="order-last lg:order-none">
            <div class="mb-[clamp(20px,3vw,26px)]">
                <h1 class="font-display text-[clamp(1.6rem,3.4vw,2.1rem)] font-normal leading-tight">Checkout</h1>
                <p class="mt-1 text-[13px] text-muted">Periksa alamat, pilih pengiriman, lalu buat pesanan.</p>
            </div>

            @if ($this->problems || $errors->has('cart'))
                <div class="mb-6 rounded-md border border-sale/30 bg-sale/5 px-4 py-3.5 text-[12.5px]" role="alert">
                    <p class="font-semibold text-sale">Ada barang yang tidak bisa dipesan</p>
                    <ul class="mt-1.5 list-disc pl-5 text-muted">
                        @foreach (array_unique([...$this->problems, ...$errors->get('cart')]) as $problem)
                            <li>{{ $problem }}</li>
                        @endforeach
                    </ul>
                    <a href="{{ route('user.cart') }}" wire:navigate class="mt-2 inline-block font-semibold text-ink underline-offset-4 hover:underline">Ubah keranjang</a>
                </div>
            @endif

            <form wire:submit="placeOrder" novalidate>
                {{-- 1. Alamat --}}
                <section class="mb-8">
                    <h2 class="mb-3.5 flex items-center gap-2 border-b border-line pb-3 text-[13px] font-semibold uppercase tracking-[0.08em]">
                        <span class="inline-block size-2 border border-ink bg-ink/20" aria-hidden="true"></span>
                        1. Alamat Pengiriman
                    </h2>

                    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                        @foreach ($this::ADDRESS_FIELDS as $field => $label)
                            <div wire:key="address-{{ $field }}" @class(['sm:col-span-2' => $field === 'street'])>
                                <label for="address-{{ $field }}" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">{{ $label }}</label>

                                @if ($field === 'street')
                                    <textarea
                                        id="address-{{ $field }}"
                                        rows="2"
                                        autocomplete="street-address"
                                        wire:model.blur="address.{{ $field }}"
                                        @class([
                                            'w-full rounded-[2px] border bg-surface px-3 py-2.5 text-[13px] outline-none transition-colors focus:border-ink',
                                            'border-sale' => $errors->has("address.{$field}"),
                                            'border-line' => ! $errors->has("address.{$field}"),
                                        ])
                                    >{{ $address[$field] ?? '' }}</textarea>
                                @else
                                    <input
                                        id="address-{{ $field }}"
                                        type="{{ $field === 'recipient_phone' ? 'tel' : 'text' }}"
                                        wire:model.blur="address.{{ $field }}"
                                        value="{{ $address[$field] ?? '' }}"
                                        @class([
                                            'h-10 w-full rounded-[2px] border bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                            'border-sale' => $errors->has("address.{$field}"),
                                            'border-line' => ! $errors->has("address.{$field}"),
                                        ])
                                    >
                                @endif

                                @error("address.{$field}")
                                    <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    @unless ($this->hasDefaultAddress)
                        <label class="mt-3.5 flex w-fit cursor-pointer items-center gap-2 text-[12.5px] text-muted">
                            <input type="checkbox" wire:model="saveAddress" @checked($saveAddress) class="size-4 accent-ink">
                            Simpan sebagai alamat utama
                        </label>
                    @endunless
                </section>

                {{-- 2. Pengiriman --}}
                <section class="mb-8">
                    <h2 class="mb-3.5 flex items-center gap-2 border-b border-line pb-3 text-[13px] font-semibold uppercase tracking-[0.08em]">
                        <span class="inline-block size-2 border border-ink bg-ink/20" aria-hidden="true"></span>
                        2. Pengiriman
                    </h2>

                    <div class="flex flex-col gap-2.5">
                        @foreach (Shipment::OPTIONS as $key => $option)
                            <label
                                wire:key="shipping-{{ $key }}"
                                class="grid cursor-pointer grid-cols-[18px_1fr_auto] items-start gap-3 rounded-[3px] border border-line p-3.5 transition-colors hover:border-muted has-checked:border-ink has-checked:bg-cream"
                            >
                                <input
                                    type="radio"
                                    name="shippingOption"
                                    value="{{ $key }}"
                                    wire:model.live="shippingOption"
                                    @checked($shippingOption === $key)
                                    class="mt-0.5 size-4 accent-ink"
                                >

                                <span>
                                    <span class="block text-[13px] font-semibold">{{ $option['label'] }}</span>
                                    <span class="mt-0.5 block text-[11.5px] text-muted">Estimasi {{ $option['eta'] }} setelah dikirim</span>
                                </span>

                                <span class="text-right text-[12.5px] font-semibold">
                                    Rp {{ number_format($option['cost'], 0, ',', '.') }}
                                    <span class="block text-[11px] font-normal text-muted">per toko</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @error('shippingOption')
                        <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                    @enderror
                </section>

                {{-- 3. Pembayaran --}}
                <section class="mb-8">
                    <h2 class="mb-3.5 flex items-center gap-2 border-b border-line pb-3 text-[13px] font-semibold uppercase tracking-[0.08em]">
                        <span class="inline-block size-2 border border-ink bg-ink/20" aria-hidden="true"></span>
                        3. Pembayaran
                    </h2>

                    <div class="grid grid-cols-2 border-b border-line">
                        @foreach (Payment::CHECKOUT_METHODS as $method)
                            <button
                                type="button"
                                wire:key="payment-{{ $method }}"
                                aria-pressed="{{ $paymentMethod === $method ? 'true' : 'false' }}"
                                wire:click="$set('paymentMethod', '{{ $method }}')"
                                @class([
                                    'cursor-pointer border-b-2 px-1 py-2.5 text-[10.5px] font-bold uppercase tracking-[0.08em] transition-colors',
                                    'border-ink text-ink' => $paymentMethod === $method,
                                    'border-transparent text-muted hover:text-ink' => $paymentMethod !== $method,
                                ])
                            >
                                {{ Payment::METHOD_LABELS[$method] }}
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        @if ($paymentMethod === 'virtual_account')
                            <label for="vaBank" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">Bank</label>
                            <select id="vaBank" wire:model="vaBank" class="h-10 w-full rounded-[2px] border border-line bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink">
                                @foreach (Payment::VA_BANKS as $code => $bank)
                                    <option value="{{ $code }}" @selected($vaBank === $code)>{{ $bank }} Virtual Account</option>
                                @endforeach
                            </select>
                            @error('vaBank')
                                <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                            @enderror
                        @endif

                        <p class="mt-3 rounded-[2px] bg-cream px-4 py-3.5 text-[12.5px] leading-relaxed text-muted">
                            {{ $paymentMethod === 'qris' ? 'Kode QRIS' : 'Nomor virtual account' }} muncul di halaman pesanan setelah pesanan dibuat.
                            Bayar dalam {{ Order::PAYMENT_WINDOW_HOURS }} jam, lewat dari itu pesanan dibatalkan otomatis.
                        </p>
                    </div>
                </section>

                {{-- 4. Catatan --}}
                <section class="mb-8">
                    <label for="note" class="mb-1.5 block text-[10.5px] font-semibold uppercase tracking-[0.1em] text-muted">Catatan untuk penjual (opsional)</label>
                    <textarea id="note" rows="2" wire:model.blur="note" maxlength="500" class="w-full rounded-[2px] border border-line bg-surface px-3 py-2.5 text-[13px] outline-none transition-colors focus:border-ink">{{ $note }}</textarea>
                    @error('note')
                        <p class="mt-1 text-[11.5px] text-sale">{{ $message }}</p>
                    @enderror
                </section>

                <button
                    type="submit"
                    @disabled($this->problems)
                    class="inline-flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-[2px] bg-ink text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <svg wire:loading wire:target="placeOrder" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.25" stroke-width="3" />
                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                    </svg>
                    <span wire:loading.remove wire:target="placeOrder">Buat Pesanan &middot; Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                    <span wire:loading wire:target="placeOrder">Memproses…</span>
                </button>

                <p class="mt-2.5 text-center text-[11px] text-muted">
                    Dana ditahan FitMate dan baru diteruskan ke penjual setelah pesanan kamu terima.
                </p>
            </form>
        </section>

        {{-- Ringkasan pesanan --}}
        <aside class="order-first h-fit rounded-md border border-line bg-surface p-[17px] lg:sticky lg:top-[calc(var(--spacing-header)+16px)] lg:order-none">
            <h2 class="mb-3.5 border-b border-line pb-3 text-[13px] font-semibold uppercase tracking-[0.08em]">Ringkasan Pesanan</h2>

            @foreach ($this->packages as $storeId => $package)
                <div wire:key="package-{{ $storeId }}" class="mb-4 border-b border-line pb-3.5 last-of-type:border-b-0">
                    <p class="mb-2.5 text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">{{ $package->first()->productVariant->product->store?->name }}</p>

                    @foreach ($package as $item)
                        @php($variant = $item->productVariant)
                        <div wire:key="item-{{ $item->id }}" class="mb-3 flex gap-3">
                            <div class="relative h-[70px] w-[55px] shrink-0 overflow-hidden rounded-[2px] border border-line bg-[linear-gradient(150deg,#cbc4b5,#948d7c)]">
                                @if ($image = $variant->product->primaryImage)
                                    <img src="{{ $image->url }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                                @endif
                            </div>

                            <div class="min-w-0">
                                <p class="text-[12px] font-semibold leading-snug">{{ $variant->product->name }}</p>
                                <p class="mt-1 text-[11px] text-muted">
                                    {{ collect([$variant->size?->name, $variant->color?->name])->filter()->join(' · ') }} &middot; {{ $item->quantity }} &times;
                                </p>
                                <p class="mt-1.5 text-[12px] font-semibold">Rp {{ number_format($item->lineTotal(), 0, ',', '.') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach

            <div class="flex justify-between text-[12px]">
                <span class="text-muted">Subtotal</span>
                <span class="font-medium">Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
            </div>

            <div class="mt-2.5 flex justify-between text-[12px]">
                <span class="text-muted">Ongkir ({{ $this->packages->count() }} paket)</span>
                <span class="font-medium">Rp {{ number_format($this->shippingTotal, 0, ',', '.') }}</span>
            </div>

            <div class="mt-2.5 flex items-baseline justify-between border-t border-line pt-3 text-[15px] font-bold">
                <span>Total</span>
                <span>Rp {{ number_format($this->total, 0, ',', '.') }}</span>
            </div>
        </aside>
    </div>
</div>
