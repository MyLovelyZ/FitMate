<?php

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Produk Saya')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Produk toko milik user yang login; kosong kalau user belum punya toko.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    #[Computed]
    public function products(): LengthAwarePaginator
    {
        return Product::with(['category', 'primaryImage', 'store'])
            ->withCount('variants')
            ->withSum('variants', 'stock')
            ->where('store_id', Auth::user()->store?->id ?? 0)
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->latest()
            ->paginate(15);
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Seller' => route('seller.dashboard'), 'Produk' => null]"
        title="Produk Saya"
        :links="['seller.dashboard' => 'Dashboard', 'seller.products.index' => 'Produk', 'seller.orders.index' => 'Pesanan']"
    />

    <div class="mt-6">
        <label for="search" class="sr-only">Cari produk</label>
        <input
            id="search"
            type="search"
            wire:model.live.debounce.400ms="search"
            value="{{ $search }}"
            placeholder="Cari nama produk…"
            class="w-full max-w-sm rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px]"
        >
    </div>

    @if ($this->products->isEmpty())
        <p class="mt-4 rounded-md border border-line bg-surface px-[18px] py-6 text-center text-[13px] text-muted">Belum ada produk.</p>
    @else
        <div wire:loading.delay.class="pointer-events-none opacity-50" class="mt-4 overflow-x-auto rounded-md border border-line bg-surface transition-opacity duration-200">
            <table class="w-full text-left text-[12.5px]">
                <thead class="text-[11px] uppercase tracking-[0.1em] text-muted">
                    <tr>
                        <th class="px-[18px] py-3 font-semibold">Produk</th>
                        <th class="px-[18px] py-3 font-semibold">Kategori</th>
                        <th class="px-[18px] py-3 font-semibold">Harga</th>
                        <th class="px-[18px] py-3 font-semibold">Varian</th>
                        <th class="px-[18px] py-3 font-semibold">Stok</th>
                        <th class="px-[18px] py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->products as $product)
                        <tr wire:key="product-{{ $product->id }}" class="border-t border-line">
                            <td class="px-[18px] py-3 font-medium">
                                <div class="flex items-center gap-3">
                                    <x-ui.product-thumb :product="$product" class="h-12 w-[38px] rounded-[2px]" />

                                    <a href="{{ route('products.show', $product) }}" wire:navigate class="hover:underline">{{ $product->name }}</a>
                                </div>
                            </td>
                            <td class="px-[18px] py-3">{{ $product->category?->name }}</td>
                            <td class="px-[18px] py-3">Rp {{ number_format((float) $product->base_price, 0, ',', '.') }}</td>
                            <td class="px-[18px] py-3">{{ $product->variants_count }}</td>
                            <td class="px-[18px] py-3">{{ (int) $product->variants_sum_stock }}</td>
                            <td class="px-[18px] py-3">
                                @if ($product->isBanned())
                                    <span class="rounded-[3px] bg-sale/10 px-2 py-0.5 text-[11px] font-semibold text-sale">Diblokir FitMate</span>
                                    <p class="mt-1 max-w-[220px] text-[11.5px] text-muted">{{ $product->ban_reason }}</p>
                                @else
                                    <span class="rounded-[3px] bg-cream px-2 py-0.5 text-[11px] font-semibold">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pager :paginator="$this->products" />
    @endif
</div>
