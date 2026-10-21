<?php

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pembayaran Berhasil')] class extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        // 404, bukan 403: pembeli lain tidak perlu tahu nomor pesanan itu ada.
        abort_unless($order->user_id === Auth::id(), 404);

        $this->order = $order;

        // Belum lunas (atau batal): tempatnya di halaman pesanan, bukan di sini.
        if (! $order->isPaid()) {
            $this->redirectRoute('user.orders.show', $order, navigate: true);
        }
    }

    /**
     * Baris rincian pesanan: label => nilai siap tampil.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function details(): array
    {
        return [
            'No. Pesanan' => $this->order->order_number,
            'Dibayar' => $this->order->paid_at?->format('d M Y, H:i') ?? '-',
            'Total' => 'Rp '.number_format((float) $this->order->grand_total, 0, ',', '.'),
        ];
    }

    /**
     * Estimasi tiba dari layanan kurir yang dipilih saat checkout.
     */
    #[Computed]
    public function deliveryEstimate(): ?string
    {
        $shipment = $this->order->storeOrders()->with('shipment')->first()?->shipment;

        foreach (Shipment::OPTIONS as $option) {
            if ($shipment && $option['courier'] === $shipment->courier && $option['service'] === $shipment->service) {
                return $option['label'].' · '.$option['eta'].' setelah penjual mengirim paket';
            }
        }

        return null;
    }
};
?>

<div class="mx-auto max-w-[800px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(56px,10vw,120px)]">
    <section class="text-center">
        <div class="mx-auto mb-[18px] flex size-[52px] items-center justify-center rounded-md bg-ink text-2xl text-cream" aria-hidden="true">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m5 12.5 4.5 4.5L19 7.5" />
            </svg>
        </div>

        <h1 class="font-display text-[clamp(1.5rem,3.2vw,2rem)] font-normal leading-tight">Pembayaran Berhasil</h1>

        <p class="mt-2 text-[13px] text-muted">Penjual sudah bisa memproses pesananmu. Dana ditahan FitMate sampai paket kamu terima.</p>
    </section>

    <section class="mt-[clamp(28px,5vw,40px)] rounded-md border border-line bg-surface">
        @foreach ($this->details as $label => $value)
            <div class="flex items-center justify-between gap-5 border-b border-line px-[18px] py-3.5 text-[12.5px] last:border-b-0">
                <span class="text-muted">{{ $label }}</span>
                <span class="font-semibold">{{ $value }}</span>
            </div>
        @endforeach
    </section>

    @if ($this->deliveryEstimate)
        <section class="mt-4 flex flex-col gap-1 rounded-md bg-cream px-[18px] py-4">
            <strong class="flex items-center gap-2 text-[12px] font-semibold">
                <span class="inline-block size-2 border border-ink bg-ink/20" aria-hidden="true"></span>
                Estimasi Pengiriman
            </strong>

            <span class="text-[12px] text-muted">{{ $this->deliveryEstimate }}</span>
        </section>
    @endif

    <div class="mt-5 flex flex-col justify-center gap-2.5 sm:flex-row">
        <a
            href="{{ route('user.orders.show', $order) }}"
            wire:navigate
            class="inline-flex min-w-[150px] items-center justify-center rounded-[2px] bg-ink px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85"
        >
            Lihat Pesanan
        </a>

        <a
            href="{{ route('products.index') }}"
            wire:navigate
            class="inline-flex min-w-[150px] items-center justify-center rounded-[2px] border border-line bg-surface px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] transition-colors hover:border-ink"
        >
            Lanjut Belanja
        </a>
    </div>
</div>
