<x-layouts.app title="Katalog">
    <x-ui.page-header title="Katalog" subtitle="Semua produk dari seluruh toko." />

    <div class="grid gap-6 lg:grid-cols-4">
        <aside class="lg:col-span-1">
            @include('products.partials.filters')
        </aside>

        <div class="flex flex-col gap-4 lg:col-span-3">
            {{-- TODO(BE-060): @foreach ($products as $product) — hati-hati N+1, pakai eager loading. --}}
            <div class="grid grid-cols-2 gap-4 xl:grid-cols-3">
                <x-product.card name="Contoh Produk" price="149000" store="Toko Contoh" :href="route('products.show', 1)" />
            </div>

            {{-- {{ $products->withQueryString()->links() }} --}}
        </div>
    </div>
</x-layouts.app>
