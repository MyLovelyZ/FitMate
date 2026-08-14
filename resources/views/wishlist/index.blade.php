<x-layouts.app title="Wishlist">
    <x-ui.page-header title="Wishlist" subtitle="Produk yang kamu simpan." />

    @if ($wishlists->isEmpty())
        <x-ui.empty-state title="Wishlist masih kosong" description="Simpan produk yang kamu incar agar mudah ditemukan lagi.">
            <x-ui.button :href="route('products.index')" size="sm">Lihat Katalog</x-ui.button>
        </x-ui.empty-state>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($wishlists as $wishlist)
                @continue ($wishlist->product === null)

                <div class="flex flex-col gap-2">
                    <x-product.card
                        :name="$wishlist->product->name"
                        :price="$wishlist->product->displayPrice()"
                        :store="$wishlist->product->store?->name"
                        :image="$wishlist->product->primaryImage?->url()"
                        :href="route('products.show', $wishlist->product)"
                    />

                    <form action="{{ route('wishlist.destroy', $wishlist->product) }}" method="POST">
                        @csrf
                        @method('DELETE')

                        <x-ui.button type="submit" variant="secondary" size="sm" class="w-full">Hapus dari Wishlist</x-ui.button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
