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

/**
 * Nota (invoice) pembeli di `/berhasil/{order}`. Status dan warnanya dibaca
 * ulang dari database setiap render, dan halaman memuat ulang sendiri tiap
 * beberapa detik, jadi perubahan dari admin/seller langsung terlihat.
 */
new class extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        // 404, bukan 403: pembeli lain tidak perlu tahu nomor pesanan itu ada.
        abort_unless($order->user_id === Auth::id(), 404);

        $this->order = $order;
    }

    /**
     * @return Collection<int, StoreOrder>
     */
    #[Computed]
    public function packages(): Collection
    {
        return $this->order->storeOrders()->with(['store', 'items', 'shipment'])->orderBy('id')->get();
    }

    #[Computed]
    public function payment(): ?Payment
    {
        return $this->order->payments()->latest('id')->first();
    }

    public function render(): View
    {
        return $this->view()->title('Nota '.$this->order->order_number);
    }
};
?>

<div wire:poll.10s.visible class="mx-auto max-w-[860px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    @php
        $order = $this->order;
        $money = fn ($amount): string => 'Rp '.number_format((float) $amount, 0, ',', '.');
    @endphp

    <x-ui.breadcrumbs :items="['Beranda' => route('home'), 'Pesanan Saya' => route('user.orders'), 'Nota' => null]" class="mb-4" />

    <section class="rounded-md border border-line bg-surface">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-line px-[clamp(16px,3vw,28px)] py-5">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Nota Pesanan</p>
                <h1 class="mt-1 font-display text-[clamp(1.4rem,3vw,1.9rem)] leading-tight">{{ $order->order_number }}</h1>
                <p class="mt-1 text-[12.5px] text-muted">Dipesan {{ ($order->placed_at ?? $order->created_at)->format('d M Y, H:i') }}</p>
            </div>

            <div class="text-right">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Status</p>
                <x-ui.order-status :order="$order" class="mt-1.5 px-3 py-1 text-[13px]" data-status="{{ $order->status }}" />
            </div>
        </header>

        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 border-b border-line px-[clamp(16px,3vw,28px)] py-5 text-[12.5px] sm:grid-cols-2">
            <div>
                <dt class="text-muted">Dikirim ke</dt>
                <dd class="mt-0.5 font-medium">{{ $order->recipient_name }} &middot; {{ $order->recipient_phone }}</dd>
                <dd class="text-muted">{{ $order->shipping_street }}, {{ $order->shipping_city }}, {{ $order->shipping_province }} {{ $order->shipping_postal_code }}</dd>
            </div>
            <div>
                <dt class="text-muted">Pembayaran</dt>
                <dd class="mt-0.5 font-medium">{{ $this->payment ? (Payment::METHOD_LABELS[$this->payment->method] ?? $this->payment->method) : '—' }}{{ $this->payment?->va_bank ? ' '.strtoupper($this->payment->va_bank) : '' }}</dd>
                <dd class="text-muted">{{ $order->paid_at ? 'Lunas '.$order->paid_at->format('d M Y, H:i') : 'Belum dibayar' }}</dd>
            </div>
        </dl>

        @foreach ($this->packages as $package)
            <div wire:key="package-{{ $package->id }}" class="border-b border-line px-[clamp(16px,3vw,28px)] py-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-[13px] font-semibold">{{ $package->store?->displayName() }}</p>
                    <span class="rounded-[3px] bg-cream px-2 py-0.5 text-[11px] font-semibold">{{ StoreOrder::BUYER_STATUS_LABELS[$package->status] ?? $package->status }}</span>
                </div>

                <ul class="mt-2 flex flex-col gap-1.5 text-[12.5px]">
                    @foreach ($package->items as $item)
                        <li class="flex justify-between gap-4">
                            <span>
                                {{ $item->product_name }}
                                <span class="text-muted">{{ collect([$item->size_label, $item->color_name])->filter()->join(' · ') }} &times; {{ $item->quantity }}</span>
                            </span>
                            <span class="shrink-0">{{ $money($item->subtotal) }}</span>
                        </li>
                    @endforeach
                </ul>

                @if ($package->shipment)
                    <p class="mt-2 text-[12px] text-muted">
                        Pengiriman: {{ Shipment::STATUS_LABELS[$package->shipment->status] ?? $package->shipment->status }}
                        @if ($package->shipment->tracking_number)
                            &middot; Resi <span class="font-semibold text-ink">{{ $package->shipment->tracking_number }}</span>
                        @endif
                    </p>
                @endif
            </div>
        @endforeach

        <dl class="flex flex-col gap-2 px-[clamp(16px,3vw,28px)] py-5 text-[12.5px]">
            <div class="flex justify-between gap-4">
                <dt class="text-muted">Subtotal</dt>
                <dd>{{ $money($order->subtotal) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted">Ongkos kirim</dt>
                <dd>{{ $money($order->shipping_total) }}</dd>
            </div>
            @if ((float) $order->discount_total > 0)
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">Diskon {{ $order->voucher_code }}</dt>
                    <dd class="text-sale">&minus;{{ $money($order->discount_total) }}</dd>
                </div>
            @endif
            <div class="mt-1 flex justify-between gap-4 border-t border-line pt-3 text-[15px] font-semibold">
                <dt>Total</dt>
                <dd>{{ $money($order->grand_total) }}</dd>
            </div>
        </dl>
    </section>

    <div class="mt-5 flex flex-col justify-center gap-2.5 sm:flex-row print:hidden">
        <a href="{{ route('user.orders.show', $order) }}" wire:navigate class="inline-flex min-w-[150px] items-center justify-center rounded-[2px] bg-ink px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85">Lacak Pesanan</a>
        <button type="button" x-on:click="window.print()" class="inline-flex min-w-[150px] cursor-pointer items-center justify-center rounded-[2px] border border-line bg-surface px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] transition-colors hover:border-ink">Cetak Nota</button>
    </div>
</div>
