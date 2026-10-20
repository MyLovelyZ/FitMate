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

        return [
            'Produk' => number_format($this->store->products()->count(), 0, ',', '.'),
            'Pesanan Baru' => number_format((clone $storeOrders)->where('status', 'pending')->count(), 0, ',', '.'),
            'Pesanan Selesai' => number_format((clone $storeOrders)->where('status', 'completed')->count(), 0, ',', '.'),
            'Pendapatan' => 'Rp '.number_format((float) (clone $storeOrders)->where('status', 'completed')->sum('seller_earning'), 0, ',', '.'),
        ];
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Seller' => null]"
        title="Dashboard Seller"
        :description="$this->store ? 'Toko '.$this->store->name.' · status '.$this->store->status : null"
        :links="['seller.dashboard' => 'Dashboard', 'seller.products.index' => 'Produk', 'seller.orders.index' => 'Pesanan']"
    />

    @if ($this->store)
        {{-- Hitungan pesanan & pendapatan dimuat lazy supaya kerangka halaman tampil lebih dulu. --}}
        @island(name: 'stats', lazy: true)
            @placeholder
                <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @foreach (range(1, 4) as $index)
                        <x-ui.skeleton class="h-[86px]" />
                    @endforeach
                </section>
            @endplaceholder

            <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
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
