<x-layouts.app title="Beranda">
    @php($hero = $banners->first())

    <section class="rounded-2xl bg-brand-600 px-6 py-12 text-white sm:px-10 sm:py-16">
        <div class="flex max-w-xl flex-col items-start gap-4">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
                {{ $hero?->title ?? 'Ukuran yang benar-benar pas.' }}
            </h1>

            <p class="text-brand-100">
                {{ $hero?->subtitle ?? 'Isi ukuran badanmu sekali. FitMate menghitung ukuran yang cocok untuk setiap produk, bukan menebak.' }}
            </p>

            <div class="flex flex-wrap gap-3">
                <x-ui.button :href="route('profile.body')" variant="secondary">Isi Ukuran Badan</x-ui.button>
                <x-ui.button :href="route('products.index')" variant="ghost" class="text-white hover:bg-brand-700">Lihat Katalog</x-ui.button>
            </div>
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section class="mt-10 flex flex-col gap-4">
            <h2 class="text-lg font-semibold">Jelajahi Kategori</h2>

            <div class="flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    @foreach ($category->children as $child)
                        <a
                            href="{{ route('products.index', ['category' => $child->slug]) }}"
                            class="rounded-full border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 transition hover:border-brand-600 hover:text-brand-700"
                        >
                            {{ $child->name }}
                        </a>
                    @endforeach
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-10 flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Produk Terbaru</h2>
            <a href="{{ route('products.index') }}" class="text-sm font-medium text-brand-600 hover:underline">Lihat semua</a>
        </div>

        @if ($latestProducts->isEmpty())
            <x-ui.empty-state title="Belum ada produk" description="Produk akan muncul di sini setelah penjual mulai menambahkannya." />
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($latestProducts as $product)
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
        @endif
    </section>
</x-layouts.app>
