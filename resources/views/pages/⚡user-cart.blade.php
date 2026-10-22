<?php

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Keranjang')] class extends Component
{
    #[Computed]
    public function cart(): ?Cart
    {
        return Cart::whereBelongsTo(Auth::user())
            ->with('items.productVariant.product.store', 'items.productVariant.product.primaryImage', 'items.productVariant.size', 'items.productVariant.color')
            ->first();
    }

    /**
     * @return Collection<int, \Illuminate\Database\Eloquent\Collection<int, CartItem>>
     */
    #[Computed]
    public function groups(): Collection
    {
        return $this->cart?->itemsGroupedByStore() ?? collect();
    }

    #[Computed]
    public function subtotal(): float
    {
        return (float) ($this->cart?->items->sum(
            fn (CartItem $item): float => (float) $item->productVariant->effective_price * $item->quantity
        ) ?? 0);
    }

    /**
     * Hanya boleh menghapus item dari keranjang milik sendiri.
     */
    public function remove(int $itemId): void
    {
        $this->ownItem($itemId)?->delete();

        $this->refreshCart();

        $this->dispatch('notify', message: 'Item dihapus dari keranjang.');
    }

    /**
     * Tambah/kurangi satu. Tidak bisa melebihi stok varian; turun dari 1 berarti
     * item dihapus.
     */
    public function changeQuantity(int $itemId, int $delta): void
    {
        $item = $this->ownItem($itemId);

        if (! $item) {
            return;
        }

        $quantity = $item->quantity + ($delta >= 0 ? 1 : -1);

        if ($quantity < 1) {
            $item->delete();
        } else {
            $item->update(['quantity' => min($quantity, max(1, $item->productVariant->stock))]);
        }

        $this->refreshCart();
    }

    protected function ownItem(int $itemId): ?CartItem
    {
        return CartItem::whereKey($itemId)
            ->whereHas('cart', fn ($query) => $query->whereBelongsTo(Auth::user()))
            ->with('productVariant')
            ->first();
    }

    /**
     * Hitung ulang isi keranjang dan kabari lencana di navbar.
     */
    protected function refreshCart(): void
    {
        unset($this->cart, $this->groups, $this->subtotal);

        $this->dispatch('cart-updated', count: Auth::user()->cartQuantity());
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Akun' => route('profile'), 'Keranjang' => null]"
        title="Keranjang"
        :links="['profile' => 'Profil', 'user.orders' => 'Pesanan', 'user.cart' => 'Keranjang']"
    />

    @if ($this->groups->isEmpty())
        <div class="mt-6 rounded-md border border-line bg-surface px-[18px] py-12 text-center">
            <p class="font-display text-xl">Keranjangmu masih kosong</p>
            <a href="{{ route('products.index') }}" wire:navigate class="mt-3 inline-block text-[12.5px] font-medium hover:underline">Lihat katalog</a>
        </div>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="flex flex-col gap-4">
                @foreach ($this->groups as $storeId => $items)
                    <section wire:key="store-{{ $storeId }}" class="rounded-md border border-line bg-surface">
                        <h2 class="border-b border-line px-[18px] py-3 text-[12px] font-semibold uppercase tracking-[0.1em]">
                            {{ $items->first()->productVariant->product->store?->name ?? 'Toko' }}
                        </h2>

                        @foreach ($items as $item)
                            <div wire:key="item-{{ $item->id }}" class="flex items-center justify-between gap-4 border-b border-line px-[18px] py-3.5 last:border-b-0">
                                <div class="flex min-w-0 items-center gap-3.5">
                                    <a
                                        href="{{ route('products.show', $item->productVariant->product) }}"
                                        wire:navigate
                                        class="relative h-[70px] w-14 shrink-0 overflow-hidden rounded-[2px] border border-line bg-cream"
                                        tabindex="-1"
                                        aria-hidden="true"
                                    >
                                        @if ($photo = $item->productVariant->product->primaryImage)
                                            <img src="{{ $photo->url }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                                        @endif
                                    </a>

                                    <div class="min-w-0">
                                        <a href="{{ route('products.show', $item->productVariant->product) }}" wire:navigate class="text-[13.5px] font-medium hover:underline">
                                            {{ $item->productVariant->product->name }}
                                        </a>
                                        <p class="mt-1 text-[12px] text-muted">
                                            {{ collect([$item->productVariant->size?->name, $item->productVariant->color?->name])->filter()->join(' · ') }}
                                            &middot; Rp {{ number_format((float) $item->productVariant->effective_price, 0, ',', '.') }}
                                        </p>

                                        @if ($item->quantity > $item->productVariant->stock)
                                            <p class="mt-1 text-[12px] text-sale">Stok tinggal {{ $item->productVariant->stock }}.</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    <div class="flex h-8 items-center rounded-[2px] border border-line" role="group" aria-label="Jumlah {{ $item->productVariant->product->name }}">
                                        <button
                                            type="button"
                                            wire:click="changeQuantity({{ $item->id }}, -1)"
                                            class="flex h-full w-8 cursor-pointer items-center justify-center text-muted transition-colors hover:text-ink data-loading:opacity-50"
                                            aria-label="Kurangi jumlah"
                                        >&minus;</button>
                                        <span class="w-7 text-center text-[13px] font-semibold">{{ $item->quantity }}</span>
                                        <button
                                            type="button"
                                            wire:click="changeQuantity({{ $item->id }}, 1)"
                                            @disabled($item->quantity >= $item->productVariant->stock)
                                            class="flex h-full w-8 cursor-pointer items-center justify-center text-muted transition-colors hover:text-ink disabled:cursor-not-allowed disabled:opacity-40 data-loading:opacity-50"
                                            aria-label="Tambah jumlah"
                                        >+</button>
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="remove({{ $item->id }})"
                                        class="cursor-pointer text-[12px] font-medium text-muted hover:text-sale"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endforeach
            </div>

            <aside class="h-fit rounded-md border border-line bg-surface px-[18px] py-5">
                <div class="flex items-center justify-between text-[13px]">
                    <span class="text-muted">Subtotal</span>
                    <span class="font-semibold">Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                </div>

                <a
                    href="{{ route('checkout') }}"
                    wire:navigate
                    class="mt-5 flex items-center justify-center rounded-[2px] bg-ink px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85"
                >
                    Checkout
                </a>
            </aside>
        </div>
    @endif
</div>
