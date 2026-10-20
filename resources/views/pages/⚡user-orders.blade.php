<?php

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pesanan Saya')] class extends Component
{
    use WithPagination;

    /** Kunci `Order::BUYER_STATUS_GROUPS`; kosong = semua pesanan. */
    #[Url(as: 'status', except: '')]
    public string $status = '';

    public function mount(): void
    {
        if (! array_key_exists($this->status, Order::BUYER_STATUS_GROUPS)) {
            $this->status = '';
        }
    }

    public function setStatus(string $status): void
    {
        $this->status = array_key_exists($status, Order::BUYER_STATUS_GROUPS) ? $status : '';

        $this->resetPage();
    }

    /**
     * Tab status: kunci => label, diawali "Semua".
     *
     * @return array<string, string>
     */
    #[Computed]
    public function tabs(): array
    {
        return ['' => 'Semua'] + array_map(fn (array $group): string => $group[0], Order::BUYER_STATUS_GROUPS);
    }

    /**
     * Jumlah pesanan per tab, dihitung dengan satu query GROUP BY.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $totalsByStatus = Order::query()
            ->whereBelongsTo(Auth::user())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = ['' => (int) $totalsByStatus->sum()];

        foreach (Order::BUYER_STATUS_GROUPS as $group => [, $statuses]) {
            $counts[$group] = (int) $totalsByStatus->only($statuses)->sum();
        }

        return $counts;
    }

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return Order::query()
            ->whereBelongsTo(Auth::user())
            ->when($this->status !== '', fn ($query) => $query->inBuyerStatusGroup($this->status))
            ->with(['storeOrders.store', 'storeOrders.items.product.primaryImage'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(8);
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Akun' => route('profile'), 'Pesanan' => null]"
        title="Pesanan Saya"
        description="Pantau pembayaran, pengemasan, dan pengiriman belanjaanmu."
        :links="['profile' => 'Profil', 'user.orders' => 'Pesanan', 'user.cart' => 'Keranjang']"
    />

    <nav class="mt-6 flex gap-2 overflow-x-auto pb-1" aria-label="Filter status pesanan">
        @foreach ($this->tabs as $key => $label)
            <button
                type="button"
                wire:key="status-tab-{{ $key ?: 'semua' }}"
                wire:click="setStatus('{{ $key }}')"
                aria-pressed="{{ $status === $key ? 'true' : 'false' }}"
                @class([
                    'inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-[2px] border px-4 py-2 text-[12.5px] font-semibold transition-colors duration-150 data-loading:opacity-60',
                    'border-ink bg-ink text-cream' => $status === $key,
                    'border-line bg-surface hover:border-ink' => $status !== $key,
                ])
            >
                {{ $label }}
                <span @class([
                    'min-w-5 rounded-full px-1.5 text-center text-[11px]',
                    'bg-cream/20' => $status === $key,
                    'bg-line/70 text-muted' => $status !== $key,
                ])>{{ $this->counts[$key] ?? 0 }}</span>
            </button>
        @endforeach
    </nav>

    {{-- Ganti tab/halaman: daftar diganti kerangka kartu sampai data baru datang. --}}
    <div wire:loading.delay.shorter.remove class="mt-4 flex flex-col gap-4">
        @forelse ($this->orders as $order)
            @php
                $items = $order->storeOrders->flatMap->items;
                $firstItem = $items->first();
                $thumbnail = $firstItem?->product?->primaryImage;
            @endphp

            <a
                wire:key="order-{{ $order->id }}"
                href="{{ route('user.orders.show', $order) }}"
                wire:navigate
                class="block rounded-md border border-line bg-surface transition-colors duration-150 hover:border-ink"
            >
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-[18px] py-3 text-[12px]">
                    <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="font-semibold">{{ $order->order_number }}</span>
                        <span class="text-muted">{{ ($order->placed_at ?? $order->created_at)->format('d M Y') }}</span>
                        <span class="truncate text-muted">{{ $order->storeOrders->pluck('store.name')->filter()->join(', ') }}</span>
                    </div>

                    <x-ui.order-status :order="$order" />
                </div>

                <div class="flex flex-col gap-4 px-[18px] py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="relative h-[72px] w-[58px] shrink-0 overflow-hidden rounded-sm border border-line bg-[linear-gradient(150deg,#d9d2c1,#b3ab98)]">
                            @if ($thumbnail)
                                <img src="{{ $thumbnail->url }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                            @endif
                        </div>

                        <div class="min-w-0">
                            <p class="truncate text-[13.5px] font-medium">{{ $firstItem?->product_name ?? 'Pesanan' }}</p>
                            @if ($firstItem)
                                <p class="mt-0.5 text-[12px] text-muted">
                                    {{ collect([$firstItem->size_label, $firstItem->color_name])->filter()->join(' · ') }}
                                    &middot; {{ $firstItem->quantity }} &times; Rp {{ number_format((float) $firstItem->unit_price, 0, ',', '.') }}
                                </p>
                            @endif
                            @if ($items->count() > 1)
                                <p class="mt-0.5 text-[12px] text-muted">+{{ $items->count() - 1 }} produk lainnya</p>
                            @endif
                        </div>
                    </div>

                    <div class="shrink-0 sm:text-right">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">Total Belanja</p>
                        <p class="mt-0.5 text-[15px] font-semibold">Rp {{ number_format((float) $order->grand_total, 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-line px-[18px] py-3.5 sm:flex-row sm:items-center sm:justify-between">
                    @if ($order->status === 'pending_payment' && $order->expires_at)
                        <p class="text-[12.5px] font-medium text-sale">Bayar sebelum {{ $order->expires_at->format('d M Y, H:i') }}</p>
                    @elseif ($order->buyerStepIndex() !== null)
                        <x-ui.order-steps :order="$order" compact class="w-full max-w-md" />
                    @else
                        <p class="text-[12.5px] text-muted">Pesanan ini tidak dilanjutkan.</p>
                    @endif

                    <span class="inline-flex shrink-0 items-center gap-1.5 text-[12.5px] font-semibold">
                        Lihat Detail
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m9 18 6-6-6-6" />
                        </svg>
                    </span>
                </div>
            </a>
        @empty
            <div class="rounded-md border border-line bg-surface px-[18px] py-12 text-center">
                <p class="font-display text-xl">
                    {{ $status === '' ? 'Belum ada pesanan' : 'Tidak ada pesanan '.str($this->tabs[$status])->lower() }}
                </p>
                <a href="{{ route('products.index') }}" wire:navigate class="mt-3 inline-block text-[12.5px] font-medium hover:underline">Mulai belanja</a>
            </div>
        @endforelse

        <x-ui.pager :paginator="$this->orders" />
    </div>

    <div wire:loading.delay.shorter.flex class="mt-4 hidden flex-col gap-4" aria-hidden="true">
        @foreach (range(1, 3) as $index)
            <x-ui.skeleton wire:key="order-skeleton-{{ $index }}" class="h-[196px]" />
        @endforeach
    </div>
</div>
