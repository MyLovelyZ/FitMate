<x-layouts.app title="Beranda">
    <section class="rounded-2xl bg-brand-600 px-6 py-12 text-white sm:px-10 sm:py-16">
        <div class="flex max-w-xl flex-col items-start gap-4">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Ukuran yang benar-benar pas.</h1>
            <p class="text-brand-100">Isi ukuran badanmu sekali. FitMate menghitung ukuran yang cocok untuk setiap produk, bukan menebak.</p>

            <div class="flex flex-wrap gap-3">
                <x-ui.button :href="route('profile.body')" variant="secondary">Isi Ukuran Badan</x-ui.button>
                <x-ui.button :href="route('products.index')" variant="ghost" class="text-white hover:bg-brand-700">Lihat Katalog</x-ui.button>
            </div>
        </div>
    </section>

    {{-- TODO(BE-066): tampilkan banner promo aktif sesuai placement. --}}

    <section class="mt-10 flex flex-col gap-4">
        <h2 class="text-lg font-semibold">Produk Terbaru</h2>

        {{-- TODO(BE-060): ganti contoh statis ini dengan @foreach ($products as $product). --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <x-product.card name="Contoh Produk" price="149000" store="Toko Contoh" fit-status="fit" :href="route('products.show', 1)" />
        </div>
    </section>
</x-layouts.app>
