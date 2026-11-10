<?php

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Produk yang disimpan pembeli. Produk yang sudah tidak dijual (toko ditutup,
 * diblokir, dihapus) tetap terlihat di sini dengan tanda "Tidak tersedia".
 */
new #[Title('Wishlist')] class extends Component
{
    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return Auth::user()->wishlist()->with(['category', 'images', 'store'])->get();
    }

    public function remove(int $productId): void
    {
        Auth::user()->wishlist()->detach($productId);

        unset($this->products);

        $this->dispatch('notify', message: 'Dihapus dari wishlist.');
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Akun' => route('profile'), 'Wishlist' => null]"
        title="Wishlist"
        description="Produk yang kamu simpan untuk dibeli nanti."
        :links="['profile' => 'Profil', 'user.orders' => 'Pesanan', 'user.wishlist' => 'Wishlist', 'user.cart' => 'Keranjang']"
    />

    @if ($this->products->isEmpty())
        <div class="mt-6 rounded-md border border-line bg-surface px-[18px] py-12 text-center">
            <p class="font-display text-xl">Wishlist masih kosong</p>
            <p class="mt-1.5 text-[13px] text-muted">Tekan "Simpan ke Wishlist" di halaman produk untuk menyimpannya di sini.</p>
            <a href="{{ route('products.index') }}" wire:navigate class="mt-5 inline-flex items-center rounded-[2px] bg-ink px-5 py-2.5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity hover:opacity-85">Lihat Katalog</a>
        </div>
    @else
        <div wire:loading.delay.class="pointer-events-none opacity-50" class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 transition-opacity md:grid-cols-3 lg:grid-cols-4">
            @foreach ($this->products as $product)
                <div wire:key="wish-{{ $product->id }}" class="flex flex-col gap-2">
                    @if ($product->isPurchasable())
                        <x-ui.product-card :product="$product" />
                    @else
                        <div class="opacity-60">
                            <x-ui.product-thumb :product="$product" class="aspect-[4/5] w-full rounded-md" />
                            <p class="mt-2 text-[13px] font-medium">{{ $product->name }}</p>
                            <p class="text-[12px] text-sale">Tidak tersedia</p>
                        </div>
                    @endif

                    <button type="button" wire:click="remove({{ $product->id }})" class="w-fit cursor-pointer text-[12px] font-semibold text-muted transition-colors hover:text-sale">Hapus dari wishlist</button>
                </div>
            @endforeach
        </div>
    @endif
</div>
