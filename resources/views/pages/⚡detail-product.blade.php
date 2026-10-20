<?php

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Product $product;

    /** Ukuran yang sedang dipilih pembeli. Null untuk produk tanpa ukuran. */
    public ?int $selectedSizeId = null;

    /** Warna yang sedang dipilih pembeli. Null untuk varian tanpa warna. */
    public ?int $selectedColorId = null;

    /** Indeks foto yang sedang tampil besar. */
    public int $activeImage = 0;

    public function mount(Product $product): void
    {
        abort_unless($product->is_active, 404);

        $this->product = $product;

        // Halaman dibuka dengan ukuran & warna pertama yang masih ada stoknya supaya
        // harga dan ketersediaan langsung terlihat tanpa pembeli harus memilih dulu.
        $preselected = $this->sizes->first(fn (array $size): bool => $size['in_stock'])
            ?? $this->sizes->first();

        $this->selectedSizeId = $preselected['id'] ?? null;
        $this->selectedColorId = $this->firstAvailableColorId();
    }

    public function selectSize(int $sizeId): void
    {
        // Nilainya datang dari klik pembeli, jadi dicocokkan dulu ke ukuran yang
        // memang dijual untuk produk ini.
        if (! $this->sizes->contains('id', $sizeId)) {
            return;
        }

        $this->selectedSizeId = $sizeId;
        $this->forgetSelection();

        // Warna yang tadi dipilih belum tentu ada stoknya di ukuran baru.
        if (! $this->colors->contains(fn (array $color): bool => $color['id'] === $this->selectedColorId && $color['in_stock'])) {
            $this->selectedColorId = $this->firstAvailableColorId();
            $this->forgetSelection();
        }
    }

    public function selectColor(int $colorId): void
    {
        if (! $this->colors->contains('id', $colorId)) {
            return;
        }

        $this->selectedColorId = $colorId;
        $this->forgetSelection();
    }

    /**
     * Tamu diarahkan ke login lalu kembali ke produk ini; akun staf tidak punya
     * keranjang. Jumlahnya dibatasi stok varian, termasuk yang sudah ada di keranjang.
     */
    public function addToCart(int $quantity = 1): void
    {
        $this->resetErrorBag('cart');

        $user = Auth::user();

        if (! $user) {
            redirect()->setIntendedUrl(route('products.show', $this->product));
            $this->redirectRoute('login', navigate: true);

            return;
        }

        if ($user->role !== 'user') {
            $this->addError('cart', 'Keranjang hanya tersedia untuk akun pembeli.');

            return;
        }

        $variant = $this->selectedVariant;

        if (! $variant?->isInStock()) {
            $this->addError('cart', 'Pilihan ini sedang habis.');

            return;
        }

        $added = Cart::forUser($user)->add($variant, max(1, $quantity));

        if ($added === 0) {
            $this->addError('cart', "Semua stok pilihan ini ({$variant->stock}) sudah ada di keranjangmu.");

            return;
        }

        $this->dispatch('cart-updated', count: $user->cartQuantity());
        $this->dispatch(
            'notify',
            message: "{$added}× {$this->product->name} masuk ke keranjang.",
            url: route('user.cart'),
            action: 'Lihat Keranjang',
        );
    }

    /**
     * Pilihan ukuran/warna berubah: hitung ulang varian yang dipilih.
     */
    protected function forgetSelection(): void
    {
        unset($this->variantsForSelectedSize, $this->colors, $this->selectedVariant, $this->isAvailable, $this->price);
    }

    protected function firstAvailableColorId(): ?int
    {
        $color = $this->colors->first(fn (array $color): bool => $color['in_stock'])
            ?? $this->colors->first();

        return $color['id'] ?? null;
    }

    /**
     * Varian aktif produk ini. Di-query ulang setiap request supaya stok dan
     * harganya selalu yang terbaru, bukan hasil hidrasi properti.
     *
     * @return EloquentCollection<int, ProductVariant>
     */
    #[Computed]
    public function variants(): EloquentCollection
    {
        return $this->product->variants()
            ->where('is_active', true)
            ->with(['size', 'color'])
            ->get();
    }

    /**
     * Ukuran yang dijual untuk produk ini, urut dari terkecil. Produk aksesoris
     * (varian tanpa `size_id`) menghasilkan daftar kosong.
     *
     * @return Collection<int, array{id: int, code: string, name: string, sort_order: int, in_stock: bool}>
     */
    #[Computed]
    public function sizes(): Collection
    {
        return $this->variants
            ->filter(fn (ProductVariant $variant): bool => $variant->size !== null)
            ->groupBy('size_id')
            ->map(fn (Enumerable $variants): array => [
                'id' => $variants->first()->size->id,
                'code' => $variants->first()->size->code,
                'name' => $variants->first()->size->name,
                'sort_order' => $variants->first()->size->sort_order,
                'in_stock' => $variants->contains(fn (ProductVariant $variant): bool => $variant->stock > 0),
            ])
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * Varian di ukuran terpilih; produk tanpa ukuran memakai semua variannya.
     *
     * @return EloquentCollection<int, ProductVariant>
     */
    #[Computed]
    public function variantsForSelectedSize(): EloquentCollection
    {
        return $this->sizes->isEmpty()
            ? $this->variants
            : $this->variants->where('size_id', $this->selectedSizeId)->values();
    }

    /**
     * Warna yang dijual di ukuran terpilih beserta ketersediaannya.
     *
     * @return Collection<int, array{id: int, name: string, hex: string, in_stock: bool}>
     */
    #[Computed]
    public function colors(): Collection
    {
        return $this->variantsForSelectedSize
            ->filter(fn (ProductVariant $variant): bool => $variant->color !== null)
            ->map(fn (ProductVariant $variant): array => [
                'id' => $variant->color->id,
                'name' => $variant->color->name,
                'hex' => $variant->color->hex_code,
                'in_stock' => $variant->isInStock(),
            ])
            ->unique('id')
            ->values()
            ->toBase();
    }

    /**
     * Varian yang akan masuk keranjang: ukuran dan warna terpilih. Kalau warnanya
     * belum dipilih (varian tanpa warna), ambil yang masih ada stoknya.
     */
    #[Computed]
    public function selectedVariant(): ?ProductVariant
    {
        $candidates = $this->variantsForSelectedSize;

        return ($this->selectedColorId ? $candidates->firstWhere('color_id', $this->selectedColorId) : null)
            ?? $candidates->first(fn (ProductVariant $variant): bool => $variant->stock > 0)
            ?? $candidates->first();
    }

    #[Computed]
    public function isAvailable(): bool
    {
        return (bool) $this->selectedVariant?->isInStock();
    }

    /**
     * Harga varian terpilih, jatuh ke harga dasar produk kalau produknya belum
     * punya varian sama sekali.
     */
    #[Computed]
    public function price(): string
    {
        $amount = $this->selectedVariant?->effective_price ?? $this->product->base_price;

        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    /**
     * Foto produk, gambar utama di depan. Produk yang belum punya foto tetap dapat
     * satu bidang warna sebagai penahan tempat. Bidang warna yang sama juga
     * tampil di belakang foto selama foto dimuat. Nama class gradiennya ditulis
     * utuh supaya tetap terpindai Tailwind.
     *
     * @return array<int, array{url: ?string, alt: string, surface: string}>
     */
    #[Computed]
    public function gallery(): array
    {
        $surface = 'bg-[linear-gradient(150deg,#cbc4b5,#948d7c)]';

        $photos = $this->product->images()->get()
            ->map(fn (ProductImage $image): array => [
                'url' => $image->url,
                'alt' => $image->alt_text ?? $this->product->name,
                'surface' => $surface,
            ]);

        return $photos->isNotEmpty()
            ? $photos->all()
            : [['url' => null, 'alt' => $this->product->name, 'surface' => $surface]];
    }

    /**
     * Foto yang tampil besar. Indeksnya bisa diubah dari sisi klien, jadi dijaga
     * tetap berada di dalam rentang galeri.
     *
     * @return array{url: ?string, alt: string, surface: string}
     */
    #[Computed]
    public function activePhoto(): array
    {
        return $this->gallery[$this->activeImage] ?? $this->gallery[0];
    }

    /**
     * Poin ringkas di bawah deskripsi, disusun dari data yang sudah ada karena
     * tabel spesifikasi produk belum dibuat.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function highlights(): array
    {
        $colors = $this->variants->pluck('color.name')->filter()->unique()->values();

        return array_values(array_filter([
            'Kategori '.$this->product->category->name,
            $colors->isNotEmpty() ? 'Pilihan warna: '.$colors->join(', ') : null,
            $this->sizes->isNotEmpty()
                ? 'Ukuran tersedia: '.$this->sizes->pluck('code')->join(', ')
                : 'Satu ukuran untuk semua',
            $this->product->store->city ? 'Dikirim dari '.$this->product->store->city : null,
        ]));
    }

    /**
     * Beranda › Katalog › jenis kategori (tersaring di katalog) › produk ini.
     *
     * @return array<string, string|null>
     */
    #[Computed]
    public function breadcrumbs(): array
    {
        $categoryType = $this->product->category->categoryType;

        return array_merge(
            ['Beranda' => route('home'), 'Katalog' => route('products.index')],
            $categoryType ? [$categoryType->name => route('products.index', ['type' => $categoryType->slug])] : [],
            [$this->product->name => null],
        );
    }

    public function render(): View
    {
        return $this->view()->title($this->product->name);
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side py-[clamp(20px,4vw,32px)]">
    <x-ui.breadcrumbs :items="$this->breadcrumbs" />

    <div class="mt-[clamp(16px,3vw,28px)] grid grid-cols-1 items-start gap-[clamp(28px,5vw,64px)] lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)]">
        {{-- Galeri: strip thumbnail di kiri, foto besar di kanan. Strip disembunyikan kalau fotonya cuma satu. --}}
        <div class="flex flex-col gap-4 sm:flex-row">
            @if (count($this->gallery) > 1)
                <div class="order-2 flex shrink-0 gap-3 overflow-x-auto sm:order-1 sm:flex-col sm:overflow-visible">
                    @foreach ($this->gallery as $index => $photo)
                        <button
                            type="button"
                            wire:key="thumb-{{ $index }}"
                            wire:click="$set('activeImage', {{ $index }})"
                            @class([
                                'relative aspect-[4/5] w-[68px] shrink-0 cursor-pointer overflow-hidden rounded-sm border transition-colors duration-150',
                                $photo['surface'],
                                'border-ink' => $activeImage === $index,
                                'border-line hover:border-muted' => $activeImage !== $index,
                            ])
                            aria-label="Lihat foto {{ $index + 1 }}"
                        >
                            @if ($photo['url'])
                                <img src="{{ $photo['url'] }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="order-1 min-w-0 flex-1 sm:order-2">
                <div class="relative aspect-[4/5] overflow-hidden rounded-md border border-line {{ $this->activePhoto['surface'] }}">
                    @if ($this->activePhoto['url'])
                        <img
                            wire:key="photo-{{ $activeImage }}"
                            src="{{ $this->activePhoto['url'] }}"
                            alt="{{ $this->activePhoto['alt'] }}"
                            fetchpriority="high"
                            class="size-full object-cover"
                        >
                    @endif
                </div>
            </div>
        </div>

        {{-- Panel informasi & pembelian --}}
        <div class="lg:pt-1.5">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">
                {{ $product->store->name }}
            </p>

            <h1 class="mt-3 font-display text-[clamp(1.9rem,4.5vw,2.9rem)] font-normal uppercase leading-[1.05] tracking-[-0.01em]">
                {{ $product->name }}
            </h1>

            <p class="mt-4 text-[17px]">{{ $this->price }}</p>

            @if ($this->sizes->isNotEmpty())
                <div class="mt-[clamp(20px,3vw,28px)] border-t border-line pt-5">
                    <div class="flex items-baseline justify-between gap-4">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">Size</span>

                        {{-- TODO(BE-062): buka tabel standar ukuran + rekomendasi dari profil badan pembeli. --}}
                        <button
                            type="button"
                            class="cursor-pointer font-display text-[13px] underline decoration-line underline-offset-4 transition-colors duration-150 hover:decoration-ink"
                        >
                            Size Guide
                        </button>
                    </div>

                    <div class="mt-3 grid grid-cols-4 gap-2.5">
                        @foreach ($this->sizes as $size)
                            <button
                                type="button"
                                wire:key="size-{{ $size['id'] }}"
                                wire:click="selectSize({{ $size['id'] }})"
                                @disabled(! $size['in_stock'])
                                @class([
                                    'flex h-11 items-center justify-center rounded-[2px] border text-[13px] font-medium transition-colors duration-150',
                                    'cursor-not-allowed border-line text-muted line-through' => ! $size['in_stock'],
                                    'border-ink bg-ink text-cream' => $size['in_stock'] && $selectedSizeId === $size['id'],
                                    'cursor-pointer border-line hover:border-ink' => $size['in_stock'] && $selectedSizeId !== $size['id'],
                                ])
                                title="{{ $size['name'] }}"
                            >
                                {{ $size['code'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($this->colors->isNotEmpty())
                <div class="mt-5">
                    <div class="flex items-baseline justify-between gap-4">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">Color</span>
                        <span class="text-[12.5px] text-muted">{{ $this->colors->firstWhere('id', $selectedColorId)['name'] ?? '' }}</span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2.5">
                        @foreach ($this->colors as $color)
                            <button
                                type="button"
                                wire:key="color-{{ $color['id'] }}"
                                wire:click="selectColor({{ $color['id'] }})"
                                @disabled(! $color['in_stock'])
                                @class([
                                    'size-[30px] rounded-full border transition-[box-shadow,border-color] duration-150',
                                    'cursor-not-allowed opacity-35' => ! $color['in_stock'],
                                    'border-ink shadow-[0_0_0_2px_var(--color-bg),0_0_0_3px_var(--color-ink)]' => $selectedColorId === $color['id'],
                                    'cursor-pointer border-line hover:border-muted' => $color['in_stock'] && $selectedColorId !== $color['id'],
                                ])
                                style="background-color: {{ $color['hex'] }}"
                                aria-pressed="{{ $selectedColorId === $color['id'] ? 'true' : 'false' }}"
                                aria-label="{{ $color['name'] }}{{ $color['in_stock'] ? '' : ' (habis)' }}"
                                title="{{ $color['name'] }}{{ $color['in_stock'] ? '' : ' — habis' }}"
                            ></button>
                        @endforeach
                    </div>
                </div>
            @endif

            @php
                $isStaff = auth()->check() && auth()->user()->role !== 'user';
                $canBuy = $this->isAvailable && ! $isStaff;
                $maxQuantity = max(1, (int) $this->selectedVariant?->stock);
            @endphp

            {{--
                Jumlah diatur di browser (Alpine) supaya tombol +/− tidak membuat request.
                `wire:key` ikut varian terpilih, jadi saat ukuran/warna berganti blok ini
                dibuat ulang dengan jumlah 1 dan batas stok yang baru. Server tetap
                membatasi ulang ke stok saat `addToCart` dipanggil.
            --}}
            <div
                wire:key="buy-{{ $this->selectedVariant?->id ?? 'none' }}"
                class="mt-5 flex gap-3"
                x-data="{ quantity: 1, adding: false }"
            >
                @if ($canBuy)
                    <div class="flex h-[54px] shrink-0 items-center rounded-[2px] border border-line" role="group" aria-label="Jumlah">
                        <button
                            type="button"
                            class="flex h-full w-10 cursor-pointer items-center justify-center text-lg text-muted transition-colors hover:text-ink disabled:cursor-not-allowed disabled:opacity-40"
                            x-on:click="quantity = Math.max(1, quantity - 1)"
                            x-bind:disabled="quantity <= 1"
                            aria-label="Kurangi jumlah"
                        >&minus;</button>
                        <span class="w-7 text-center text-[14px] font-semibold" x-text="quantity" aria-live="polite">1</span>
                        <button
                            type="button"
                            class="flex h-full w-10 cursor-pointer items-center justify-center text-lg text-muted transition-colors hover:text-ink disabled:cursor-not-allowed disabled:opacity-40"
                            x-on:click="quantity = Math.min({{ $maxQuantity }}, quantity + 1)"
                            x-bind:disabled="quantity >= {{ $maxQuantity }}"
                            aria-label="Tambah jumlah"
                        >+</button>
                    </div>
                @endif

                <button
                    type="button"
                    x-on:click="adding = true; $wire.addToCart(quantity).finally(() => { adding = false; quantity = 1 })"
                    x-bind:disabled="adding || {{ $canBuy ? 'false' : 'true' }}"
                    @disabled(! $canBuy)
                    class="flex h-[54px] w-full cursor-pointer items-center justify-center gap-3 rounded-[2px] bg-ink px-8 text-[13.5px] font-semibold uppercase tracking-[0.04em] text-cream transition-opacity duration-200 hover:opacity-85 disabled:cursor-not-allowed disabled:bg-line disabled:text-muted disabled:hover:opacity-100"
                >
                    @if ($isStaff)
                        Khusus Akun Pembeli
                    @elseif ($this->isAvailable)
                        <svg wire:loading wire:target="addToCart" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.25" stroke-width="3" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        <span wire:loading.remove wire:target="addToCart">Add to Bag</span>
                        <span wire:loading wire:target="addToCart">Menambahkan…</span>

                        <svg wire:loading.remove wire:target="addToCart" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14m0 0-6-6m6 6-6 6" />
                        </svg>
                    @else
                        Sold Out
                    @endif
                </button>
            </div>

            @error('cart')
                <p class="mt-2 text-[12.5px] text-sale" role="alert">{{ $message }}</p>
            @enderror

            <p class="mt-[clamp(20px,3vw,28px)] text-[13.5px] leading-[1.75] text-muted">
                {{ $product->description }}
            </p>

            <ul class="mt-4 list-none space-y-1.5">
                @foreach ($this->highlights as $highlight)
                    <li wire:key="highlight-{{ $loop->index }}" class="flex gap-2 text-[13.5px] text-muted">
                        <span aria-hidden="true">&bull;</span>
                        <span>{{ $highlight }}</span>
                    </li>
                @endforeach
            </ul>

            <div x-data="{ open: false }" class="mt-[clamp(24px,4vw,36px)] border-t border-line">
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between gap-4 py-5 text-left"
                    x-on:click="open = ! open"
                    x-bind:aria-expanded="open"
                    aria-controls="shippingReturns"
                >
                    <span class="text-[11px] font-semibold uppercase tracking-[0.18em]">Shipping &amp; Returns</span>

                    <span class="relative size-3 shrink-0" aria-hidden="true">
                        <span class="absolute left-0 top-1/2 h-px w-3 -translate-y-1/2 bg-ink"></span>
                        <span
                            class="absolute left-1/2 top-0 h-3 w-px -translate-x-1/2 bg-ink transition-transform duration-200"
                            x-bind:class="open ? 'scale-y-0' : ''"
                        ></span>
                    </span>
                </button>

                <div id="shippingReturns" x-show="open" x-cloak class="pb-5 text-[13px] leading-[1.75] text-muted">
                    <p>
                        Pengiriman reguler 2&ndash;5 hari kerja dari
                        {{ $product->store->city ?? 'gudang penjual' }}. Ongkos kirim dihitung saat checkout.
                    </p>

                    <p class="mt-2">
                        Penukaran ukuran dibuka 7 hari setelah paket diterima, selama label masih menempel
                        dan barangnya belum dipakai.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
