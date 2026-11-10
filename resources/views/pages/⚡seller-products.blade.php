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
     * Seller menayangkan atau menyembunyikan produknya sendiri. Blokir FitMate
     * (`banned_at`) terpisah, jadi produk yang diblokir tetap tidak tampil.
     */
    public function toggleActive(int $productId): void
    {
        $product = $this->ownedProduct($productId);
        $product->update(['is_active' => ! $product->is_active]);

        unset($this->products);

        $this->dispatch('notify', message: $product->is_active ? "\"{$product->name}\" ditayangkan." : "\"{$product->name}\" disembunyikan dari katalog.");
    }

    /**
     * Soft delete: riwayat pesanan tetap utuh karena `order_items` menyimpan salinan.
     */
    public function delete(int $productId): void
    {
        $product = $this->ownedProduct($productId);
        $product->delete();

        unset($this->products);

        $this->dispatch('notify', message: "\"{$product->name}\" dihapus.");
    }

    protected function ownedProduct(int $productId): Product
    {
        $product = Product::where('store_id', Auth::user()->store?->id ?? 0)->find($productId);

        abort_if($product === null, 404);

        return $product;
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
        :breadcrumbs="['Beranda' => route('home'), 'Seller Center' => route('seller.dashboard'), 'Produk' => null]"
        title="Produk Saya"
        :links="auth()->user()->sellerAreaLinks()"
    />

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <div class="w-full max-w-sm">
            <label for="search" class="sr-only">Cari produk</label>
            <input
                id="search"
                type="search"
                wire:model.live.debounce.400ms="search"
                value="{{ $search }}"
                placeholder="Cari nama produk…"
                class="w-full rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px]"
            >
        </div>

        @if (auth()->user()->store)
            <a href="{{ route('seller.products.create') }}" wire:navigate class="inline-flex items-center rounded-[2px] bg-ink px-5 py-2.5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity hover:opacity-85">+ Tambah Produk</a>
        @endif
    </div>

    @if ($this->products->isEmpty())
        <p class="mt-4 rounded-md border border-line bg-surface px-[18px] py-6 text-center text-[13px] text-muted">
            Belum ada produk.
            @if (auth()->user()->store && $search === '')
                <a href="{{ route('seller.products.create') }}" wire:navigate class="font-semibold text-ink underline underline-offset-4">Tambah produk pertamamu</a>.
            @endif
        </p>
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
                        <th class="px-[18px] py-3"><span class="sr-only">Aksi</span></th>
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
                            <td class="px-[18px] py-3">
                                <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                                    <a href="{{ route('seller.products.edit', $product) }}" wire:navigate class="inline-flex items-center rounded-[2px] border border-line px-3 py-1.5 text-[12px] font-semibold transition-colors hover:border-ink">Ubah</a>
                                    <button type="button" wire:click="toggleActive({{ $product->id }})" class="cursor-pointer rounded-[2px] border border-line px-3 py-1.5 text-[12px] font-semibold transition-colors hover:border-ink">
                                        {{ $product->is_active ? 'Sembunyikan' : 'Tayangkan' }}
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="delete({{ $product->id }})"
                                        wire:confirm="Hapus produk ini? Produk hilang dari katalog dan keranjang pembeli, riwayat pesanan tetap tersimpan."
                                        class="cursor-pointer px-2 py-1.5 text-[12px] font-semibold text-muted transition-colors hover:text-sale"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pager :paginator="$this->products" />
    @endif
</div>
