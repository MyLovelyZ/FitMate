<?php

use App\Models\Shipment;
use App\Models\StoreOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * Status dana toko di escrow seperti yang dibaca seller.
     *
     * @var array<string, string>
     */
    public const ESCROW_LABELS = [
        'held' => 'Ditahan FitMate',
        'released' => 'Sudah masuk saldo',
        'refunded' => 'Dikembalikan ke pembeli',
        'partially_refunded' => 'Sebagian dikembalikan',
        'cancelled' => 'Dibatalkan',
    ];

    public StoreOrder $storeOrder;

    public string $trackingNumber = '';

    public string $cancelReason = '';

    public function mount(StoreOrder $storeOrder): void
    {
        // 404, bukan 403: seller tidak perlu tahu nomor paket toko lain itu ada.
        abort_unless($storeOrder->store_id === Auth::user()->store?->id, 404);

        $this->storeOrder = $storeOrder;
        $this->trackingNumber = (string) $storeOrder->shipment?->tracking_number;
    }

    /**
     * @return Collection<int, \App\Models\OrderItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return $this->storeOrder->items()->with('product.primaryImage')->orderBy('id')->get();
    }

    #[Computed]
    public function courierLabel(): ?string
    {
        $shipment = $this->storeOrder->shipment;

        if (! $shipment) {
            return null;
        }

        foreach (Shipment::OPTIONS as $option) {
            if ($option['courier'] === $shipment->courier && $option['service'] === $shipment->service) {
                return $option['label'];
            }
        }

        return strtoupper($shipment->courier).($shipment->service ? ' '.strtoupper($shipment->service) : '');
    }

    public function process(): void
    {
        abort_unless($this->storeOrder->canTransitionTo('processing') && ! $this->storeOrder->isAwaitingPayment(), 403);

        $this->storeOrder->process();

        $this->refreshStoreOrder('Pesanan diproses. Kemas barangnya, lalu isi nomor resi.');
    }

    public function ship(): void
    {
        abort_unless($this->storeOrder->canTransitionTo('shipped'), 403);

        $this->validate(['trackingNumber' => ['required', 'string', 'max:64']], attributes: ['trackingNumber' => 'nomor resi']);

        $this->storeOrder->ship(trim($this->trackingNumber));

        $this->refreshStoreOrder('Paket ditandai dikirim.');
    }

    /**
     * Sementara menggantikan update status dari kurir (belum ada integrasi API kurir).
     */
    public function markDelivered(): void
    {
        abort_unless($this->storeOrder->canTransitionTo('delivered'), 403);

        $this->storeOrder->markDelivered();

        $this->refreshStoreOrder('Paket ditandai sudah sampai.');
    }

    public function cancel(): void
    {
        abort_unless($this->storeOrder->canTransitionTo('cancelled') && ! $this->storeOrder->isAwaitingPayment(), 403);

        $this->validate(['cancelReason' => ['required', 'string', 'max:255']], attributes: ['cancelReason' => 'alasan pembatalan']);

        $this->storeOrder->cancel('Dibatalkan penjual: '.trim($this->cancelReason), Auth::user());

        $this->reset('cancelReason');
        $this->refreshStoreOrder('Pesanan dibatalkan. Dana dikembalikan ke saldo pembeli.');
    }

    protected function refreshStoreOrder(string $message): void
    {
        $this->storeOrder->refresh();

        unset($this->items, $this->courierLabel);

        $this->dispatch('notify', message: $message);
    }

    public function render(): View
    {
        return $this->view()->title('Pesanan '.$this->storeOrder->store_order_number);
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    @php
        $order = $storeOrder->order;
        $hold = $storeOrder->escrowHold;
    @endphp

    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Seller' => route('seller.dashboard'), 'Pesanan' => route('seller.orders.index'), $storeOrder->store_order_number => null]"
        :title="$storeOrder->store_order_number"
        :description="'Dipesan '.($order->placed_at ?? $order->created_at)->format('d M Y, H:i').' oleh '.$order->user?->name"
        :links="['seller.dashboard' => 'Dashboard', 'seller.products.index' => 'Produk', 'seller.orders.index' => 'Pesanan']"
    />

    <div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="flex flex-col gap-4">
            {{-- Langkah berikutnya untuk seller --}}
            <section class="rounded-md border border-line bg-surface px-[clamp(16px,3vw,24px)] py-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Status</h2>
                    <span class="rounded-[3px] bg-cream px-2 py-0.5 text-[11.5px] font-semibold">
                        {{ $storeOrder->isAwaitingPayment() && $storeOrder->status === 'pending' ? 'Menunggu Pembayaran' : (StoreOrder::BUYER_STATUS_LABELS[$storeOrder->status] ?? $storeOrder->status) }}
                    </span>
                </div>

                <div class="mt-4 text-[13px]">
                    @if ($storeOrder->status === 'pending' && $storeOrder->isAwaitingPayment())
                        <p class="text-muted">
                            Pembeli belum membayar. Jangan kemas barang dulu
                            @if ($order->expires_at)
                                — batas bayarnya {{ $order->expires_at->format('d M Y, H:i') }}, lewat dari itu pesanan batal otomatis
                            @endif
                            .
                        </p>
                    @elseif ($storeOrder->status === 'pending')
                        <p class="text-muted">Pembayaran sudah diterima FitMate. Proses pesanan ini lalu kemas barangnya.</p>
                        <button
                            type="button"
                            wire:click="process"
                            class="mt-3 inline-flex items-center justify-center rounded-[2px] bg-ink px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85 disabled:cursor-wait disabled:opacity-60"
                        >
                            Proses Pesanan
                        </button>
                    @elseif ($storeOrder->status === 'processing')
                        <form wire:submit="ship" class="flex flex-col gap-2">
                            <label for="trackingNumber" class="text-[12px] font-semibold">
                                Nomor resi {{ $this->courierLabel }}
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <input
                                    id="trackingNumber"
                                    type="text"
                                    wire:model="trackingNumber"
                                    value="{{ $trackingNumber }}"
                                    maxlength="64"
                                    class="h-11 min-w-0 flex-1 rounded-[2px] border border-line bg-bg px-3 text-[13px] outline-none transition-colors focus:border-ink"
                                >
                                <button
                                    type="submit"
                                    class="inline-flex h-11 items-center justify-center rounded-[2px] bg-ink px-5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85 disabled:cursor-wait disabled:opacity-60"
                                >
                                    Kirim Paket
                                </button>
                            </div>
                            @error('trackingNumber') <p class="text-[12px] text-sale">{{ $message }}</p> @enderror
                        </form>
                    @elseif ($storeOrder->status === 'shipped')
                        <p class="text-muted">Paket dalam perjalanan. Pembeli bisa menekan "Pesanan Diterima" kapan saja.</p>
                        <button
                            type="button"
                            wire:click="markDelivered"
                            wire:confirm="Tandai paket ini sudah sampai di alamat pembeli?"
                            class="mt-3 inline-flex items-center justify-center rounded-[2px] border border-line px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] transition-colors hover:border-ink"
                        >
                            Tandai Sudah Sampai (Simulasi Kurir)
                        </button>
                    @elseif ($storeOrder->status === 'delivered')
                        <p class="text-muted">
                            Paket sudah sampai, menunggu konfirmasi pembeli.
                            @if ($hold?->auto_release_at)
                                Kalau pembeli tidak mengeluh, dana cair otomatis pada {{ $hold->auto_release_at->format('d M Y') }}.
                            @endif
                        </p>
                    @elseif ($storeOrder->status === 'completed')
                        <p class="text-muted">Selesai. Pendapatan Rp {{ number_format((float) $storeOrder->seller_earning, 0, ',', '.') }} sudah masuk saldo tokomu.</p>
                    @elseif ($storeOrder->status === 'cancelled')
                        <p class="text-muted">{{ $storeOrder->note ?? 'Pesanan ini dibatalkan.' }}</p>
                    @else
                        <p class="text-muted">{{ StoreOrder::BUYER_STATUS_LABELS[$storeOrder->status] ?? $storeOrder->status }}.</p>
                    @endif
                </div>

                @if ($storeOrder->canTransitionTo('cancelled') && ! $storeOrder->isAwaitingPayment())
                    <div x-data="{ cancelling: false }" class="mt-5 border-t border-line pt-4">
                        <button
                            type="button"
                            x-show="! cancelling"
                            x-on:click="cancelling = true"
                            class="cursor-pointer text-[12px] font-semibold text-muted transition-colors hover:text-sale"
                        >
                            Batalkan pesanan ini
                        </button>

                        <form x-show="cancelling" x-cloak wire:submit="cancel" class="flex flex-col gap-2">
                            <label for="cancelReason" class="text-[12px] font-semibold">Alasan pembatalan (dibaca pembeli)</label>
                            <textarea
                                id="cancelReason"
                                rows="2"
                                wire:model="cancelReason"
                                maxlength="255"
                                placeholder="Mis. stok ternyata rusak"
                                class="w-full rounded-[2px] border border-line bg-bg px-3 py-2.5 text-[13px] outline-none transition-colors placeholder:text-muted focus:border-ink"
                            >{{ $cancelReason }}</textarea>
                            @error('cancelReason') <p class="text-[12px] text-sale">{{ $message }}</p> @enderror

                            <div class="flex gap-2">
                                <button
                                    type="submit"
                                    wire:confirm="Batalkan pesanan? Stok dikembalikan dan seluruh dana paket ini dikembalikan ke pembeli."
                                    class="cursor-pointer rounded-[2px] bg-sale px-4 py-2.5 text-[11px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity hover:opacity-85"
                                >
                                    Batalkan Pesanan
                                </button>
                                <button type="button" x-on:click="cancelling = false" class="cursor-pointer px-3 text-[12px] font-semibold text-muted hover:text-ink">Tidak jadi</button>
                            </div>
                        </form>
                    </div>
                @endif
            </section>

            {{-- Barang --}}
            <section class="rounded-md border border-line bg-surface">
                <h2 class="border-b border-line px-[18px] py-3 text-[12px] font-semibold uppercase tracking-[0.1em]">Barang ({{ $this->items->sum('quantity') }})</h2>

                <ul class="divide-y divide-line">
                    @foreach ($this->items as $item)
                        <li wire:key="item-{{ $item->id }}" class="flex items-center gap-3 px-[18px] py-3.5">
                            <div class="relative h-[72px] w-[58px] shrink-0 overflow-hidden rounded-sm border border-line bg-[linear-gradient(150deg,#d9d2c1,#b3ab98)]">
                                @if ($image = $item->product?->primaryImage)
                                    <img src="{{ $image->url }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-[13.5px] font-medium">{{ $item->product_name }}</p>
                                <p class="mt-0.5 text-[12px] text-muted">
                                    {{ collect([$item->size_label, $item->color_name, $item->variant_sku])->filter()->join(' · ') }}
                                    &middot; {{ $item->quantity }} &times; Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}
                                </p>
                            </div>

                            <p class="shrink-0 text-[13px] font-semibold">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</p>
                        </li>
                    @endforeach
                </ul>

                @if ($order->note)
                    <p class="border-t border-line px-[18px] py-3 text-[12.5px] text-muted">Catatan pembeli: {{ $order->note }}</p>
                @endif
            </section>
        </div>

        <aside class="flex flex-col gap-4">
            <section class="rounded-md border border-line bg-surface px-[18px] py-4">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Kirim ke</h2>
                <p class="mt-3 text-[13px] font-medium">{{ $order->recipient_name }} &middot; {{ $order->recipient_phone }}</p>
                <p class="mt-1 text-[12.5px] leading-relaxed text-muted">
                    {{ $order->shipping_street }}, {{ $order->shipping_city }}, {{ $order->shipping_province }} {{ $order->shipping_postal_code }}
                </p>

                @if ($shipment = $storeOrder->shipment)
                    <dl class="mt-3 flex flex-col gap-1.5 border-t border-line pt-3 text-[12.5px]">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Kurir</dt>
                            <dd class="font-medium">{{ $this->courierLabel }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Status</dt>
                            <dd>{{ Shipment::STATUS_LABELS[$shipment->status] ?? $shipment->status }}</dd>
                        </div>
                        @if ($shipment->tracking_number)
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted">No. Resi</dt>
                                <dd class="font-medium tracking-[0.04em]">{{ $shipment->tracking_number }}</dd>
                            </div>
                        @endif
                    </dl>
                @endif
            </section>

            <section class="rounded-md border border-line bg-surface px-[18px] py-4">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Rincian Dana</h2>

                <dl class="mt-3 flex flex-col gap-2 text-[12.5px]">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted">Subtotal barang</dt>
                        <dd>Rp {{ number_format((float) $storeOrder->subtotal, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted">Ongkos kirim</dt>
                        <dd>Rp {{ number_format((float) $storeOrder->shipping_cost, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted">Komisi FitMate ({{ rtrim(rtrim(number_format((float) $storeOrder->platform_fee_rate * 100, 2), '0'), '.') }}%)</dt>
                        <dd class="text-sale">&minus;Rp {{ number_format((float) $storeOrder->platform_fee_amount, 0, ',', '.') }}</dd>
                    </div>
                    <div class="mt-1 flex justify-between gap-4 border-t border-line pt-3 text-[14px] font-semibold">
                        <dt>Pendapatanmu</dt>
                        <dd>Rp {{ number_format((float) $storeOrder->seller_earning, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted">Status dana</dt>
                        <dd>{{ $hold ? ($this::ESCROW_LABELS[$hold->status] ?? $hold->status) : 'Belum dibayar' }}</dd>
                    </div>
                </dl>
            </section>
        </aside>
    </div>
</div>
