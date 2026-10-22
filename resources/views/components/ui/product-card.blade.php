{{--
    Kartu produk di grid (katalog, halaman toko). Produk wajib di-eager-load dengan
    `category` dan `images`. Atribut tambahan (mis. `wire:key`) diteruskan ke `<a>`.

    Bidang warna di belakang foto dipetakan dari id supaya tiap kartu konsisten
    antar halaman; nama class gradiennya ditulis utuh supaya terpindai Tailwind.
--}}
@props(['product'])

@php
    $surfaces = [
        'bg-[linear-gradient(150deg,#cbc4b5,#948d7c)]',
        'bg-[linear-gradient(150deg,#d9d2c1,#b3ab98)]',
        'bg-[linear-gradient(150deg,#c3bcac,#8b8474)]',
        'bg-[linear-gradient(150deg,#e0dacd,#a8a091)]',
    ];
@endphp

<a
    href="{{ route('products.show', $product) }}"
    wire:navigate
    {{ $attributes->class('group flex flex-col') }}
>
    <div class="relative aspect-[4/5] overflow-hidden rounded-md border border-line {{ $surfaces[$product->id % count($surfaces)] }}">
        @if ($photo = $product->images->first())
            <img
                src="{{ $photo->url }}"
                alt="{{ $photo->alt_text ?? $product->name }}"
                loading="lazy"
                decoding="async"
                class="absolute inset-0 size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
            >
        @endif

        {{-- Foto kedua muncul saat kartu disorot, jadi pembeli langsung lihat sisi lain produknya. --}}
        @if ($hoverPhoto = $product->images->get(1))
            <img
                src="{{ $hoverPhoto->url }}"
                alt=""
                loading="lazy"
                decoding="async"
                class="absolute inset-0 size-full object-cover opacity-0 transition-opacity duration-500 group-hover:opacity-100"
            >
        @endif

        @if ($product->isOnSale())
            <span class="absolute left-3 top-3 z-1 rounded-[3px] bg-sale px-2 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-cream">
                Sale
            </span>
        @endif

        <span class="absolute bottom-3 left-3 z-1 rounded-[3px] bg-cream/92 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-ink opacity-0 transition-opacity duration-200 group-hover:opacity-100">
            {{ $product->category->name }}
        </span>
    </div>

    <p class="mt-3 text-[13.5px] leading-[1.45] transition-colors duration-150 group-hover:text-muted">
        {{ $product->name }}
    </p>

    <p class="mt-1.5 flex flex-wrap items-baseline gap-x-2 text-[13.5px] font-medium">
        <span @class(['text-sale' => $product->isOnSale()])>Rp {{ number_format((float) $product->base_price, 0, ',', '.') }}</span>

        @if ($product->isOnSale())
            <s class="text-[12px] font-normal text-muted">Rp {{ number_format((float) $product->compare_at_price, 0, ',', '.') }}</s>
        @endif
    </p>
</a>
