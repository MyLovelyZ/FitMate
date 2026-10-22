<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\StoreOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public const PAYMENT_METHODS = Payment::METHOD_LABELS;

    public Order $order;

    public function mount(Order $order): void
    {
        // 404, bukan 403: pembeli lain tidak perlu tahu nomor pesanan itu ada.
        abort_unless($order->user_id === Auth::id(), 404);

        $this->order = $order;
    }

    /**
     * Paket per toko beserta pengiriman dan barangnya.
     *
     * @return Collection<int, StoreOrder>
     */
    #[Computed]
    public function storeOrders(): Collection
    {
        return $this->order->storeOrders()
            ->with(['store', 'shipment', 'escrowHold', 'items.product.primaryImage', 'items.product.store'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Instruksi bayar terakhir yang masih menunggu, untuk pesanan yang belum dibayar.
     */
    #[Computed]
    public function pendingPayment(): ?Payment
    {
        return $this->order->payments()
            ->where('status', 'pending')
            ->latest()
            ->first();
    }

    /**
     * Tanggal di bawah tiap langkah progres. Pengiriman dan penyelesaian diambil
     * dari paket per toko: dikirim = paket pertama yang dikirim, selesai = paket
     * terakhir yang selesai.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function stepDates(): array
    {
        $shippedAt = $this->storeOrders->pluck('shipped_at')->filter()->min();
        $completedAt = $this->order->status === 'completed'
            ? $this->storeOrders->pluck('completed_at')->filter()->max()
            : null;

        return array_map(
            fn ($date): string => $date->format('d M, H:i'),
            array_filter([
                'belum-dibayar' => $this->order->placed_at ?? $this->order->created_at,
                'diproses' => $this->order->paid_at,
                'dikirim' => $shippedAt,
                'selesai' => $completedAt,
            ])
        );
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function shipmentLabels(): array
    {
        return Shipment::STATUS_LABELS;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function packageLabels(): array
    {
        return StoreOrder::BUYER_STATUS_LABELS;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function breadcrumbs(): array
    {
        return [
            'Beranda' => route('home'),
            'Akun' => route('profile'),
            'Pesanan' => route('user.orders'),
            $this->order->order_number => null,
        ];
    }

    /**
     * Pengganti halaman bayar gateway selama KEP-3 belum diputuskan. Lewat jalur
     * yang sama dengan webhook (`Payment::settle()`), jadi escrow, status order,
     * dan idempotensinya ikut teruji.
     */
    public function pay(): void
    {
        $payment = $this->pendingPayment;

        if (! $payment || $this->order->status !== 'pending_payment') {
            return;
        }

        if ($payment->isExpired()) {
            $this->order->cancelUnpaid('expired');
            $this->refreshOrder();
            $this->dispatch('notify', message: 'Batas waktu pembayaran sudah lewat, pesanan dibatalkan.');

            return;
        }

        $payment->settle(Payment::SIMULATOR.'-'.$payment->reference, ['source' => 'tombol simulasi di halaman pesanan']);

        $this->redirectRoute('checkout.success', $this->order, navigate: true);
    }

    public function cancelOrder(): void
    {
        if ($this->order->cancelUnpaid()) {
            $this->dispatch('notify', message: 'Pesanan dibatalkan dan stok dikembalikan.');
        }

        $this->refreshOrder();
    }

    /**
     * Pembeli menekan "Pesanan Diterima" untuk satu paket: paket selesai dan
     * dana toko itu diteruskan ke penjual.
     */
    public function confirmReceived(int $storeOrderId): void
    {
        $storeOrder = $this->order->storeOrders()->whereKey($storeOrderId)->firstOrFail();

        abort_unless(in_array($storeOrder->status, ['shipped', 'delivered'], true), 403);

        $storeOrder->complete('buyer_confirmed');

        $this->refreshOrder();
        $this->dispatch('notify', message: 'Terima kasih! Dana sudah diteruskan ke penjual.');
    }

    protected function refreshOrder(): void
    {
        $this->order->refresh();

        unset($this->storeOrders, $this->pendingPayment, $this->stepDates);
    }

    public function render(): View
    {
        return $this->view()->title('Pesanan '.$this->order->order_number);
    }
};
?>

<div class="mx-auto max-w-[1120px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.breadcrumbs :items="$this->breadcrumbs" />

    <header class="mt-4 flex flex-wrap items-end justify-between gap-4 border-b border-line pb-5">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">Detail Pesanan</p>
            <h1 class="mt-1.5 font-display text-[clamp(1.5rem,3.2vw,2rem)] font-normal leading-tight">{{ $order->order_number }}</h1>
            <p class="mt-1 text-[13px] text-muted">Dipesan {{ ($order->placed_at ?? $order->created_at)->format('d M Y, H:i') }}</p>
        </div>

        <x-ui.order-status :order="$order" class="px-3 py-1 text-[12px]" />
    </header>

    {{-- Progres --}}
    <section class="mt-6 rounded-md border border-line bg-surface px-[clamp(16px,3vw,28px)] py-6">
        @if ($order->buyerStepIndex() !== null)
            <x-ui.order-steps :order="$order" :dates="$this->stepDates" />
        @else
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 size-5 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M15 9l-6 6M9 9l6 6" />
                </svg>
                <div>
                    <p class="text-[14px] font-semibold">{{ $order->buyerStatusLabel() }}</p>
                    <p class="mt-0.5 text-[12.5px] text-muted">
                        @if ($order->status === 'expired')
                            Batas waktu pembayaran sudah lewat, jadi pesanan ini dibatalkan otomatis.
                        @elseif ($order->status === 'refunded')
                            Dana pesanan ini sudah dikembalikan ke saldo FitMate-mu.
                        @else
                            Pesanan ini dibatalkan dan tidak akan diproses.
                        @endif
                    </p>
                </div>
            </div>
        @endif
    </section>

    {{-- Instruksi bayar untuk pesanan yang belum dibayar --}}
    @if ($order->status === 'pending_payment')
        <section class="mt-4 rounded-md border border-sale/30 bg-sale/5 px-[clamp(16px,3vw,28px)] py-5">
            <p class="text-[13.5px] font-semibold text-sale">Menunggu pembayaran</p>
            <p class="mt-1 text-[12.5px] text-muted">
                Selesaikan pembayaran sebesar
                <span class="font-semibold text-ink">Rp {{ number_format((float) $order->grand_total, 0, ',', '.') }}</span>
                @if ($order->expires_at)
                    sebelum <span class="font-semibold text-ink">{{ $order->expires_at->format('d M Y, H:i') }}</span>
                @endif
                supaya pesanan bisa diproses penjual.
            </p>

            @if ($payment = $this->pendingPayment)
                <dl class="mt-4 grid gap-3 text-[12.5px] sm:grid-cols-3">
                    <div>
                        <dt class="text-muted">Metode</dt>
                        <dd class="font-semibold">{{ $this::PAYMENT_METHODS[$payment->method] ?? $payment->method }}</dd>
                    </div>
                    @if ($payment->qr_string)
                        <div class="sm:col-span-2">
                            <dt class="text-muted">Kode QRIS</dt>
                            <dd class="truncate font-mono text-[12px]">{{ $payment->qr_string }}</dd>
                        </div>
                    @endif
                    @if ($payment->va_number)
                        <div x-data="{ copied: false }">
                            <dt class="text-muted">Nomor {{ strtoupper((string) $payment->va_bank) }} Virtual Account</dt>
                            <dd class="flex items-center gap-2 font-semibold tracking-[0.04em]">
                                {{ $payment->va_number }}
                                <button
                                    type="button"
                                    class="cursor-pointer text-[11.5px] font-semibold text-muted underline underline-offset-2 hover:text-ink"
                                    x-on:click="navigator.clipboard.writeText('{{ $payment->va_number }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    x-text="copied ? 'Tersalin' : 'Salin'"
                                >Salin</button>
                            </dd>
                        </div>
                    @endif
                </dl>
            @endif

            <div class="mt-5 flex flex-wrap gap-2.5">
                <button
                    type="button"
                    wire:click="pay"
                    class="inline-flex items-center justify-center gap-2 rounded-[2px] bg-ink px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85 disabled:cursor-wait disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="pay">Bayar Sekarang (Simulasi)</span>
                    <span wire:loading wire:target="pay">Memproses…</span>
                </button>

                <button
                    type="button"
                    wire:click="cancelOrder"
                    wire:confirm="Batalkan pesanan ini? Barangnya akan dikembalikan ke stok toko."
                    class="cursor-pointer rounded-[2px] px-4 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] text-muted transition-colors hover:text-sale"
                >
                    Batalkan Pesanan
                </button>
            </div>

            <p class="mt-2.5 text-[11.5px] text-muted">Payment gateway belum terpasang; tombol simulasi menandai pembayaran ini lunas.</p>
        </section>
    @endif

    <div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        {{-- Paket per toko --}}
        <div class="flex flex-col gap-4">
            @foreach ($this->storeOrders as $storeOrder)
                <section wire:key="package-{{ $storeOrder->id }}" class="rounded-md border border-line bg-surface">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-[18px] py-3">
                        <div class="min-w-0">
                            <p class="truncate text-[13.5px] font-semibold">{{ $storeOrder->store?->name ?? 'Toko' }}</p>
                            <p class="text-[11.5px] text-muted">{{ $storeOrder->store_order_number }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-[12px] font-semibold">{{ $this->packageLabels[$storeOrder->status] ?? $storeOrder->status }}</span>

                            @if (in_array($storeOrder->status, ['shipped', 'delivered'], true))
                                <button
                                    type="button"
                                    wire:click="confirmReceived({{ $storeOrder->id }})"
                                    wire:confirm="Pesanan dari {{ $storeOrder->store?->name ?? 'toko ini' }} sudah kamu terima dengan baik? Dana akan diteruskan ke penjual."
                                    class="cursor-pointer rounded-[2px] bg-ink px-3.5 py-2 text-[10.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85"
                                >
                                    Pesanan Diterima
                                </button>
                            @endif
                        </div>
                    </div>

                    @if ($storeOrder->status === 'delivered' && $storeOrder->escrowHold?->auto_release_at)
                        <p class="border-b border-line px-[18px] py-2.5 text-[12px] text-muted">
                            Paket sudah sampai. Kalau tidak ada keluhan, dana diteruskan otomatis ke penjual pada {{ $storeOrder->escrowHold->auto_release_at->format('d M Y') }}.
                        </p>
                    @elseif ($storeOrder->status === 'cancelled' && $storeOrder->note)
                        <p class="border-b border-line px-[18px] py-2.5 text-[12px] text-muted">Dibatalkan: {{ $storeOrder->note }}</p>
                    @endif

                    @if (($shipment = $storeOrder->shipment) && $order->isPaid() && $storeOrder->status !== 'cancelled')
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line bg-bg/60 px-[18px] py-3 text-[12.5px]">
                            <div>
                                <p class="font-semibold">
                                    {{ strtoupper($shipment->courier) }}{{ $shipment->service ? ' · '.strtoupper($shipment->service) : '' }}
                                    &mdash; {{ $this->shipmentLabels[$shipment->status] ?? $shipment->status }}
                                </p>
                                <p class="mt-0.5 text-muted">
                                    @if ($shipment->delivered_at)
                                        Diterima {{ $shipment->delivered_at->format('d M Y, H:i') }}
                                    @elseif ($shipment->shipped_at)
                                        Dikirim {{ $shipment->shipped_at->format('d M Y, H:i') }}
                                    @else
                                        Penjual sedang menyiapkan paket.
                                    @endif
                                </p>
                            </div>

                            @if ($shipment->tracking_number)
                                <div x-data="{ copied: false }" class="text-right">
                                    <p class="text-muted">No. Resi</p>
                                    <p class="flex items-center gap-2 font-semibold tracking-[0.04em]">
                                        {{ $shipment->tracking_number }}
                                        <button
                                            type="button"
                                            class="cursor-pointer text-[11.5px] font-semibold text-muted underline underline-offset-2 hover:text-ink"
                                            x-on:click="navigator.clipboard.writeText('{{ $shipment->tracking_number }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            x-text="copied ? 'Tersalin' : 'Salin'"
                                        >Salin</button>
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif

                    <ul class="divide-y divide-line">
                        @foreach ($storeOrder->items as $item)
                            <li wire:key="item-{{ $item->id }}" class="flex items-center gap-3 px-[18px] py-3.5">
                                <x-ui.product-thumb :product="$item->product" class="h-[72px] w-[58px] rounded-sm" />

                                <div class="min-w-0 flex-1">
                                    @if ($item->product?->isPurchasable())
                                        <a href="{{ route('products.show', $item->product) }}" wire:navigate class="text-[13.5px] font-medium hover:underline">{{ $item->product_name }}</a>
                                    @else
                                        <p class="text-[13.5px] font-medium">{{ $item->product_name }}</p>
                                    @endif
                                    <p class="mt-0.5 text-[12px] text-muted">
                                        {{ collect([$item->size_label, $item->color_name])->filter()->join(' · ') }}
                                        &middot; {{ $item->quantity }} &times; Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}
                                    </p>
                                </div>

                                <p class="shrink-0 text-[13px] font-semibold">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        {{-- Ringkasan --}}
        <aside class="flex flex-col gap-4">
            <section class="rounded-md border border-line bg-surface px-[18px] py-4">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Rincian Pembayaran</h2>

                <dl class="mt-3 flex flex-col gap-2 text-[12.5px]">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted">Subtotal</dt>
                        <dd>Rp {{ number_format((float) $order->subtotal, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted">Ongkos Kirim</dt>
                        <dd>Rp {{ number_format((float) $order->shipping_total, 0, ',', '.') }}</dd>
                    </div>
                    @if ((float) $order->discount_total > 0)
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Diskon{{ $order->voucher_code ? " ({$order->voucher_code})" : '' }}</dt>
                            <dd class="text-sale">&minus;Rp {{ number_format((float) $order->discount_total, 0, ',', '.') }}</dd>
                        </div>
                    @endif
                    <div class="mt-1 flex justify-between gap-4 border-t border-line pt-3 text-[14px] font-semibold">
                        <dt>Total</dt>
                        <dd>Rp {{ number_format((float) $order->grand_total, 0, ',', '.') }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-md border border-line bg-surface px-[18px] py-4">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Alamat Pengiriman</h2>
                <p class="mt-3 text-[13px] font-medium">{{ $order->recipient_name }} &middot; {{ $order->recipient_phone }}</p>
                <p class="mt-1 text-[12.5px] leading-relaxed text-muted">
                    {{ $order->shipping_street }}, {{ $order->shipping_city }}, {{ $order->shipping_province }} {{ $order->shipping_postal_code }}, {{ $order->shipping_country }}
                </p>

                @if ($order->note)
                    <p class="mt-3 border-t border-line pt-3 text-[12px] text-muted">Catatan: {{ $order->note }}</p>
                @endif
            </section>

            <a
                href="{{ route('user.orders') }}"
                wire:navigate.hover
                class="inline-flex items-center justify-center rounded-[2px] border border-line bg-surface px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] transition-colors hover:border-ink"
            >
                Kembali ke Pesanan
            </a>
        </aside>
    </div>
</div>
