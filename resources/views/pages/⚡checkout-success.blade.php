<?php

use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Payment Successful')] class extends Component
{
    /**
     * Ringkasan pesanan hasil flash dari halaman checkout.
     *
     * Halaman ini juga bisa dibuka langsung (mis. lewat riwayat browser), jadi
     * nilai contoh dipakai saat flash session sudah tidak ada.
     *
     * @var array{id: string, placed_at: string, total: float, email: string, delivery_window: string}
     */
    public array $order = [];

    public function mount(): void
    {
        $this->order = session('checkout.order', $this->placeholderOrder());
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
            'Order ID' => '#'.$this->order['id'],
            'Date' => $this->order['placed_at'],
            'Total Amount' => '$'.number_format((float) $this->order['total'], 2),
        ];
    }

    /**
     * @return array{id: string, placed_at: string, total: float, email: string, delivery_window: string}
     */
    protected function placeholderOrder(): array
    {
        return [
            'id' => 'FM-'.Carbon::now()->format('Y').'-0000',
            'placed_at' => Carbon::now()->format('M j, Y'),
            'total' => 900.00,
            'email' => '',
            'delivery_window' => Carbon::now()->addDays(5)->format('F j').'–'.Carbon::now()->addDays(7)->format('j, Y'),
        ];
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

        <h1 class="font-display text-[clamp(1.5rem,3.2vw,2rem)] font-normal leading-tight">Payment Successful</h1>

        <p class="mt-2 text-[13px] text-muted">Your order has been confirmed and is now being processed.</p>

        @if ($order['email'] !== '')
            <p class="mt-1 text-[12px] text-muted">A receipt has been sent to {{ $order['email'] }}.</p>
        @endif
    </section>

    <section class="mt-[clamp(28px,5vw,40px)] rounded-md border border-line bg-surface">
        @foreach ($this->details as $label => $value)
            <div class="flex items-center justify-between gap-5 border-b border-line px-[18px] py-3.5 text-[12.5px] last:border-b-0">
                <span class="text-muted">{{ $label }}</span>
                <span class="font-semibold">{{ $value }}</span>
            </div>
        @endforeach
    </section>

    <section class="mt-4 flex flex-col gap-1 rounded-md bg-cream px-[18px] py-4">
        <strong class="flex items-center gap-2 text-[12px] font-semibold">
            <span class="inline-block size-2 border border-ink bg-ink/20" aria-hidden="true"></span>
            Estimated Delivery
        </strong>

        <span class="text-[12px] text-muted">{{ $order['delivery_window'] }}</span>
    </section>

    <div class="mt-5 flex flex-col justify-center gap-2.5 sm:flex-row">
        <a
            href="{{ route('home') }}"
            wire:navigate
            class="inline-flex min-w-[150px] items-center justify-center rounded-[2px] bg-ink px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85"
        >
            Back to Home
        </a>

        <a
            href="{{ route('checkout') }}"
            wire:navigate
            class="inline-flex min-w-[150px] items-center justify-center rounded-[2px] border border-line bg-surface px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] transition-colors hover:border-ink"
        >
            View Order Details
        </a>
    </div>
</div>
