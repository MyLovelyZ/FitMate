<x-layouts.app title="Katalog">
    <x-ui.page-header title="Katalog" subtitle="Semua produk dari seluruh toko.">
        <x-slot:actions>
            <form action="{{ route('products.index') }}" method="GET" class="flex items-center gap-2">
                @foreach (request()->except(['sort', 'page']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach

                <x-ui.select name="sort" :options="$sortOptions" :selected="request('sort', 'latest')" class="w-44" x-on:change="$el.form.submit()" />
            </form>
        </x-slot>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-4">
        <aside class="lg:col-span-1">
            @include('products.partials.filters')
        </aside>

        <div class="flex flex-col gap-4 lg:col-span-3">
            @if ($products->isEmpty())
                <x-ui.empty-state title="Tidak ada produk yang cocok" description="Coba longgarkan filternya atau ganti kata kuncinya.">
                    <x-ui.button :href="route('products.index')" size="sm" variant="secondary">Reset Filter</x-ui.button>
                </x-ui.empty-state>
            @else
                <p class="text-sm text-gray-500">{{ $products->total() }} produk ditemukan.</p>

                <div class="grid grid-cols-2 gap-4 xl:grid-cols-3">
                    @foreach ($products as $product)
                        <x-product.card
                            :name="$product->name"
                            :price="$product->displayPrice()"
                            :store="$product->store?->name"
                            :rating="$product->rating_count > 0 ? $product->rating_average : null"
                            :image="$product->primaryImage?->url()"
                            :href="route('products.show', $product)"
                        />
                    @endforeach
                </div>

                {{ $products->withQueryString()->links() }}
            @endif
        </div>
    </div>
</x-layouts.app>
