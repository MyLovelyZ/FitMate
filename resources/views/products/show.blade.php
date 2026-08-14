<x-layouts.app :title="$product->name">
    @php
        $images = $product->images;
        $variants = $product->variants->where('is_active', true);
        $sizes = $variants->whereNotNull('size_chart_entry_id')
            ->unique('size_chart_entry_id')
            ->sortBy(fn ($variant) => $variant->sizeChartEntry?->sort_order);
        $colors = $variants->whereNotNull('color_name')->unique('color_name');
        $recommendedEntryId = $recommendation?->entry?->id;
    @endphp

    <div class="grid gap-8 lg:grid-cols-2">
        <div class="flex flex-col gap-3" x-data="{ active: 0 }">
            <div class="aspect-square overflow-hidden rounded-xl bg-gray-100">
                @foreach ($images as $index => $image)
                    <img
                        src="{{ $image->url() }}"
                        alt="{{ $image->alt_text ?? $product->name }}"
                        class="h-full w-full object-cover"
                        x-show="active === {{ $index }}"
                        x-cloak
                    >
                @endforeach
            </div>

            @if ($images->count() > 1)
                <div class="grid grid-cols-4 gap-3">
                    @foreach ($images as $index => $image)
                        <button
                            type="button"
                            class="aspect-square overflow-hidden rounded-lg bg-gray-100 ring-inset"
                            x-bind:class="active === {{ $index }} ? 'ring-2 ring-brand-600' : 'ring-1 ring-gray-200'"
                            x-on:click="active = {{ $index }}"
                        >
                            <img src="{{ $image->url() }}" alt="" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge>{{ $product->category?->name }}</x-ui.badge>

                    @if ($product->brand)
                        <x-ui.badge variant="brand">{{ $product->brand->name }}</x-ui.badge>
                    @endif
                </div>

                <h1 class="text-2xl font-semibold tracking-tight">{{ $product->name }}</h1>

                <p class="text-sm text-gray-500">
                    {{ $product->store?->name }}
                    @if ($product->rating_count > 0)
                        &middot; &#9733; {{ number_format((float) $product->rating_average, 1) }} ({{ $product->rating_count }} ulasan)
                    @endif
                </p>

                <p class="text-2xl font-bold">Rp{{ number_format($product->displayPrice(), 0, ',', '.') }}</p>
            </div>

            @include('products.partials.size-recommendation')

            @if ($variants->isEmpty())
                <x-ui.alert variant="warning" :dismissible="false">Produk ini belum punya varian yang bisa dibeli.</x-ui.alert>
            @else
                <form
                    action="{{ route('cart.store') }}"
                    method="POST"
                    class="flex flex-col gap-4"
                    x-data="{
                        variants: {{ Js::from($variants->map(fn ($variant) => [
                            'id' => $variant->id,
                            'size' => $variant->size_chart_entry_id,
                            'color' => $variant->color_name,
                            'stock' => $variant->stock,
                            'price' => (float) $variant->price,
                        ])->values()) }},
                        size: {{ Js::from($sizes->first()?->size_chart_entry_id) }},
                        color: {{ Js::from($colors->first()?->color_name) }},
                        qty: 1,
                        get selected() {
                            return this.variants.find((variant) => variant.size === this.size && variant.color === this.color) ?? null;
                        },
                    }"
                >
                    @csrf

                    <input type="hidden" name="product_variant_id" x-bind:value="selected?.id">

                    @if ($sizes->isNotEmpty())
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">Ukuran</span>

                                @if ($recommendation?->hasSuggestion())
                                    <span class="text-xs text-brand-700">Disarankan: {{ $recommendation->label() }}</span>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-2">
                                @foreach ($sizes as $variant)
                                    <button
                                        type="button"
                                        class="rounded-lg px-4 py-2 text-sm ring-inset"
                                        x-bind:class="size === {{ $variant->size_chart_entry_id }} ? 'bg-brand-600 text-white ring-1 ring-brand-600' : 'ring-1 ring-gray-300 hover:ring-brand-600'"
                                        x-on:click="size = {{ $variant->size_chart_entry_id }}"
                                    >
                                        {{ $variant->sizeChartEntry?->label }}
                                        @if ($variant->size_chart_entry_id === $recommendedEntryId)
                                            <span class="ml-1 text-xs">&#9733;</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($colors->isNotEmpty())
                        <div class="flex flex-col gap-2">
                            <span class="text-sm font-medium">Warna</span>

                            <div class="flex flex-wrap gap-2">
                                @foreach ($colors as $variant)
                                    <button
                                        type="button"
                                        class="rounded-lg px-4 py-2 text-sm ring-inset"
                                        x-bind:class="color === {{ Js::from($variant->color_name) }} ? 'bg-brand-600 text-white ring-1 ring-brand-600' : 'ring-1 ring-gray-300 hover:ring-brand-600'"
                                        x-on:click="color = {{ Js::from($variant->color_name) }}"
                                    >
                                        {{ $variant->color_name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <p class="text-sm text-gray-500">
                        <template x-if="selected">
                            <span>Stok tersisa <span x-text="selected.stock"></span>.</span>
                        </template>

                        <template x-if="! selected">
                            <span class="text-amber-700">Kombinasi ukuran dan warna ini tidak tersedia.</span>
                        </template>
                    </p>

                    <div class="flex items-center gap-3">
                        <div class="flex items-center rounded-lg ring-1 ring-gray-300 ring-inset">
                            <button type="button" class="px-3 py-2" x-on:click="qty = Math.max(1, qty - 1)">&minus;</button>
                            <input type="number" name="quantity" class="w-14 border-0 text-center text-sm focus:ring-0" x-model="qty" min="1">
                            <button type="button" class="px-3 py-2" x-on:click="qty++">+</button>
                        </div>

                        <x-ui.button type="submit" class="flex-1" x-bind:disabled="! selected || selected.stock < 1">
                            Masukkan Keranjang
                        </x-ui.button>
                    </div>
                </form>
            @endif

            @auth
                <form action="{{ route('wishlist.toggle', $product) }}" method="POST">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm" class="w-full">Simpan ke Wishlist</x-ui.button>
                </form>
            @endauth

            <x-ui.card heading="Deskripsi">
                <p class="text-sm whitespace-pre-line text-gray-600">{{ $product->description ?? 'Belum ada deskripsi.' }}</p>
            </x-ui.card>
        </div>
    </div>

    <section class="mt-10">
        <h2 class="mb-4 text-lg font-semibold">Ulasan Pembeli</h2>

        @if ($product->reviews->isEmpty())
            <x-ui.empty-state title="Belum ada ulasan" description="Ulasan muncul setelah pembeli menerima pesanannya." />
        @else
            <div class="flex flex-col gap-3">
                @foreach ($product->reviews as $review)
                    <x-ui.card>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $review->user?->name ?? 'Pembeli' }}</span>
                            <span class="text-sm text-amber-600">&#9733; {{ $review->rating }}</span>

                            @if ($review->is_verified_purchase)
                                <x-ui.badge variant="success">Pembelian terverifikasi</x-ui.badge>
                            @endif

                            @if ($review->fit_feedback)
                                <x-ui.badge>Ukuran: {{ $review->fit_feedback->label() }}</x-ui.badge>
                            @endif

                            @if ($review->size_purchased)
                                <x-ui.badge>Beli ukuran {{ $review->size_purchased }}</x-ui.badge>
                            @endif
                        </div>

                        @if ($review->comment)
                            <p class="mt-2 text-sm text-gray-600">{{ $review->comment }}</p>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </section>

    @if ($relatedProducts->isNotEmpty())
        <section class="mt-10">
            <h2 class="mb-4 text-lg font-semibold">Produk Serupa</h2>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($relatedProducts as $related)
                    <x-product.card
                        :name="$related->name"
                        :price="$related->displayPrice()"
                        :store="$related->store?->name"
                        :image="$related->primaryImage?->url()"
                        :href="route('products.show', $related)"
                    />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
