<?php

use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard Seller')] class extends Component
{
    /**
     * Toko milik user yang login. Admin juga boleh masuk area seller, tapi
     * admin tidak punya toko, jadi nilainya bisa null.
     */
    #[Computed]
    public function store(): ?Store
    {
        return Auth::user()->store;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function stats(): array
    {
        $storeOrders = $this->store->storeOrders();
        $wallet = Auth::user()->wallet;

        return [
            'Produk' => number_format($this->store->products()->count(), 0, ',', '.'),
            // Hanya yang sudah dibayar: paket dari order yang belum lunas belum boleh dikemas.
            'Pesanan Baru' => number_format((clone $storeOrders)->where('status', 'pending')->whereHas('order', fn ($query) => $query->whereNotNull('paid_at'))->count(), 0, ',', '.'),
            'Pesanan Selesai' => number_format((clone $storeOrders)->where('status', 'completed')->count(), 0, ',', '.'),
            'Pendapatan' => 'Rp '.number_format((float) (clone $storeOrders)->where('status', 'completed')->sum('seller_earning'), 0, ',', '.'),
            'Dana Ditahan' => 'Rp '.number_format((float) $wallet?->balance_pending, 0, ',', '.'),
            'Saldo' => 'Rp '.number_format((float) $wallet?->balance_available, 0, ',', '.'),
        ];
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Seller' => null]"
        title="Dashboard Seller"
        :description="$this->store ? 'Toko '.$this->store->name.' · '.$this->store->statusLabel() : null"
        :links="['seller.dashboard' => 'Dashboard', 'seller.products.index' => 'Produk', 'seller.orders.index' => 'Pesanan']"
    />

    @if ($this->store?->status === 'suspended')
        <p class="mt-6 rounded-md border border-sale/30 bg-sale/5 px-[18px] py-3.5 text-[13px] text-sale" role="alert">
            Toko ditangguhkan admin{{ $this->store->review_note ? ': '.$this->store->review_note : '.' }}
            Produkmu tidak tampil di katalog sampai toko diaktifkan lagi.
        </p>
    @elseif ($this->store?->canSell())
        <a href="{{ route('stores.show', $this->store) }}" wire:navigate.hover class="mt-4 inline-flex items-center gap-1.5 text-[12.5px] font-medium hover:underline">
            Lihat halaman tokomu
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
        </a>
    @endif

    @if ($this->store)
        {{-- Hitungan pesanan & pendapatan dimuat lazy supaya kerangka halaman tampil lebih dulu. --}}
        @island(name: 'stats', lazy: true)
            @placeholder
                <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-3">
                    @foreach (range(1, 6) as $index)
                        <x-ui.skeleton class="h-[86px]" />
                    @endforeach
                </section>
            @endplaceholder

            <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-3">
                @foreach ($this->stats as $label => $value)
                    <x-ui.stat :label="$label" :value="$value" />
                @endforeach
            </section>
        @endisland
    @else
        <div class="mt-6 rounded-md border border-line bg-surface px-[18px] py-12 text-center">
            <p class="font-display text-xl">Belum punya toko</p>
            <p class="mt-1.5 text-[13px] text-muted">Akun ini belum terhubung ke toko mana pun.</p>
        </div>
    @endif
</div>
