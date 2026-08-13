<x-layouts.app title="Detail Produk">
    <div class="grid gap-8 lg:grid-cols-2">
        <div class="flex flex-col gap-3" x-data="{ active: 0 }">
            {{-- TODO(BE-053): galeri gambar produk, urut sort_order, is_primary tampil pertama. --}}
            <div class="aspect-square rounded-xl bg-gray-100"></div>

            <div class="grid grid-cols-4 gap-3">
                <button type="button" class="aspect-square rounded-lg bg-gray-100" x-on:click="active = 0"></button>
            </div>
        </div>

        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold tracking-tight">Nama Produk</h1>
                <p class="text-sm text-gray-500">Toko Contoh &middot; &#9733; 0.0</p>
                <p class="text-2xl font-bold">Rp0</p>
            </div>

            @include('products.partials.size-recommendation')

            <form action="#" method="POST" class="flex flex-col gap-4" x-data="{ variant: null, qty: 1 }">
                @csrf

                {{-- TODO(BE-052): render varian (ukuran + warna) dari product_variants. --}}
                <div class="flex flex-col gap-2">
                    <span class="text-sm font-medium">Ukuran</span>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-lg px-4 py-2 text-sm ring-1 ring-gray-300 ring-inset hover:ring-brand-600">M</button>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center rounded-lg ring-1 ring-gray-300 ring-inset">
                        <button type="button" class="px-3 py-2" x-on:click="qty = Math.max(1, qty - 1)">&minus;</button>
                        <input type="number" name="quantity" class="w-14 border-0 text-center text-sm focus:ring-0" x-model="qty" min="1">
                        <button type="button" class="px-3 py-2" x-on:click="qty++">+</button>
                    </div>

                    <x-ui.button type="submit" class="flex-1">Masukkan Keranjang</x-ui.button>
                </div>
            </form>

            <x-ui.card heading="Deskripsi">
                <p class="text-sm text-gray-600">Deskripsi produk.</p>
            </x-ui.card>
        </div>
    </div>

    <section class="mt-10">
        <h2 class="mb-4 text-lg font-semibold">Ulasan Pembeli</h2>

        {{-- TODO(BE-080): daftar product_reviews beserta fit_feedback pembeli. --}}
        <x-ui.empty-state title="Belum ada ulasan" description="Ulasan muncul setelah pembeli menerima pesanannya." />
    </section>
</x-layouts.app>
