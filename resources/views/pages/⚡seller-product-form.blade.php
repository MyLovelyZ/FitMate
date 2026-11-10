<?php

use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Formulir tambah & ubah produk Seller Center. Form-nya POST biasa ber-enctype
 * multipart/form-data ke `SellerProductController` (bukan wire:submit), karena
 * membawa file foto dan divalidasi di server oleh `SellerProductRequest`.
 * Isi formulirnya komponen bersama `x-product.form` (dipakai juga admin).
 */
new class extends Component
{
    public ?Product $product = null;

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            // 404, bukan 403: seller tidak perlu tahu produk toko lain itu ada.
            abort_unless($product->isOwnedBy(Auth::user()), 404);

            $this->product = $product;
        }

        // Livewire menimpa bag `default` saat render, jadi error dari
        // SellerProductRequest dikirim lewat bag `product` lalu dipindah ke sini.
        $errors = session('errors');

        if ($errors instanceof ViewErrorBag && $errors->hasBag('product')) {
            $this->setErrorBag($errors->getBag('product'));
        }
    }

    #[Computed]
    public function store(): ?Store
    {
        return Auth::user()->store;
    }

    public function render(): View
    {
        return $this->view()->title($this->product ? 'Ubah '.$this->product->name : 'Tambah Produk');
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Seller Center' => route('seller.dashboard'), 'Produk' => route('seller.products.index'), ($product ? 'Ubah Produk' : 'Tambah Produk') => null]"
        :title="$product ? $product->name : 'Tambah Produk'"
        :description="$product ? 'Ubah detail, foto, varian, dan stok produk ini.' : 'Isi detail produk, unggah foto, lalu atur varian ukuran, warna, dan stok.'"
        :links="auth()->user()->sellerAreaLinks()"
    />

    @if (! $this->store)
        <div class="mt-6 rounded-md border border-line bg-surface px-[18px] py-12 text-center">
            <p class="font-display text-xl">Belum punya toko</p>
            <p class="mt-1.5 text-[13px] text-muted">Produk hanya bisa ditambahkan oleh pemilik toko.</p>
        </div>
    @else
        @if ($product?->isBanned())
            <p class="mt-6 rounded-md border border-sale/30 bg-sale/5 px-[18px] py-3.5 text-[13px] text-sale" role="alert">
                Produk ini diblokir FitMate: {{ $product->ban_reason }}. Perubahan tetap tersimpan, tapi produk tidak tampil sampai blokirnya dibuka admin.
            </p>
        @elseif (! $this->store->canSell())
            <p class="mt-6 rounded-md border border-line bg-cream px-[18px] py-3.5 text-[13px]" role="status">
                Tokomu belum tampil di katalog ({{ $this->store->statusLabel() }}{{ $this->store->needsProfile() ? ', profil belum dilengkapi' : '' }}). Produk tetap bisa disiapkan sekarang.
            </p>
        @endif

        {{-- novalidate: aturan isian ditegakkan di server (SellerProductRequest), bukan oleh browser. --}}
        <form
            method="POST"
            action="{{ $product ? route('seller.products.update', $product) : route('seller.products.store') }}"
            enctype="multipart/form-data"
            novalidate
        >
            @csrf
            @if ($product)
                @method('PUT')
            @endif

            <x-product.fields
                :product="$product"
                :images="$product?->images()->get() ?? collect()"
                :options="App\Models\Product::formOptions()"
                :rows="$product?->variantFormRows() ?? []"
                :bag="$errors->getBag('default')"
                :cancel-url="route('seller.products.index')"
            />
        </form>
    @endif
</div>
