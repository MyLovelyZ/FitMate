{{--
    Foto kecil produk di daftar (keranjang, checkout, pesanan, produk seller).
    Selama produknya masih dijual, menyentuh fotonya membuka halaman produk. Link-nya
    disembunyikan dari tab dan pembaca layar karena nama produk di sebelahnya sudah
    link ke halaman yang sama. Ukuran & sudut diatur pemanggil lewat `class`.
--}}
@props(['product' => null])

@php
    $photo = $product?->primaryImage;
    $isLinked = (bool) $product?->isPurchasable();
@endphp

@if ($isLinked)
    <a
        href="{{ route('products.show', $product) }}"
        wire:navigate
        tabindex="-1"
        aria-hidden="true"
        {{ $attributes->class('relative block shrink-0 overflow-hidden border border-line bg-[linear-gradient(150deg,#d9d2c1,#b3ab98)]') }}
    >
        @if ($photo)
            <img src="{{ $photo->url }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
        @endif
    </a>
@else
    <div {{ $attributes->class('relative shrink-0 overflow-hidden border border-line bg-[linear-gradient(150deg,#d9d2c1,#b3ab98)]') }}>
        @if ($photo)
            <img src="{{ $photo->url }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
        @endif
    </div>
@endif
