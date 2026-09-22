<?php

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Product $product;

    /** Ukuran yang sedang dipilih pembeli. Null untuk produk tanpa ukuran. */
    public ?int $selectedSizeId = null;

    /** Indeks foto yang sedang tampil besar. */
    public int $activeImage = 0;

    public function mount(Product $product): void
    {
        abort_unless($product->is_active, 404);

        $this->product = $product;

        // Halaman dibuka dengan ukuran pertama yang masih ada stoknya supaya harga
        // dan ketersediaan langsung terlihat tanpa pembeli harus memilih dulu.
        $preselected = $this->sizes->first(fn (array $size): bool => $size['in_stock'])
            ?? $this->sizes->first();

        $this->selectedSizeId = $preselected['id'] ?? null;
    }

    public function selectSize(int $sizeId): void
    {
        // Nilainya datang dari klik pembeli, jadi dicocokkan dulu ke ukuran yang
        // memang dijual untuk produk ini.
        if (! $this->sizes->contains('id', $sizeId)) {
            return;
        }

        $this->selectedSizeId = $sizeId;
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
     * Varian yang akan masuk keranjang: ukuran terpilih, warna pertama yang masih
     * ada stoknya.
     */
    #[Computed]
    public function selectedVariant(): ?ProductVariant
    {
        $candidates = $this->sizes->isEmpty()
            ? $this->variants
            : $this->variants->where('size_id', $this->selectedSizeId);

        return $candidates->first(fn (ProductVariant $variant): bool => $variant->stock > 0)
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
     * Foto produk. Tabel gambar produk belum ada (BE-053), jadi selama varian
     * belum mengisi kolom `image`, galeri memakai bidang warna sebagai penahan
     * tempat. Nama class gradiennya ditulis utuh supaya tetap terpindai Tailwind.
     *
     * @return array<int, array{image: ?string, surface: string}>
     */
    #[Computed]
    public function gallery(): array
    {
        $surfaces = [
            'bg-[linear-gradient(150deg,#cbc4b5,#948d7c)]',
            'bg-[linear-gradient(150deg,#d9d2c1,#b3ab98)]',
            'bg-[linear-gradient(150deg,#c3bcac,#8b8474)]',
            'bg-[linear-gradient(150deg,#e0dacd,#a8a091)]',
        ];

        $images = $this->variants->pluck('image')->filter()->unique()->values();

        return collect($surfaces)
            ->map(fn (string $surface, int $index): array => [
                'image' => $images->get($index),
                'surface' => $surface,
            ])
            ->all();
    }

    /**
     * Foto yang tampil besar. Indeksnya bisa diubah dari sisi klien, jadi dijaga
     * tetap berada di dalam rentang galeri.
     *
     * @return array{image: ?string, surface: string}
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

    public function render(): View
    {
        return $this->view()->title($this->product->name);
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side py-[clamp(20px,4vw,32px)]">
    <a
        href="{{ route('home') }}#categories"
        wire:navigate
        class="inline-flex items-center gap-2 text-[13px] text-muted transition-colors duration-150 hover:text-ink"
    >
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M19 12H5m0 0 6-6m-6 6 6 6" />
        </svg>
        Back
    </a>

    <div class="mt-[clamp(16px,3vw,28px)] grid grid-cols-1 items-start gap-[clamp(28px,5vw,64px)] lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)]">
        {{-- Galeri: strip thumbnail di kiri, foto besar di kanan. --}}
        <div class="flex flex-col gap-4 sm:flex-row">
            <div class="order-2 flex shrink-0 gap-3 sm:order-1 sm:flex-col">
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
                        @if ($photo['image'])
                            <img src="{{ asset('storage/'.$photo['image']) }}" alt="" class="size-full object-cover">
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="order-1 min-w-0 flex-1 sm:order-2">
                <div class="relative aspect-[4/5] overflow-hidden rounded-md border border-line {{ $this->activePhoto['surface'] }}">
                    @if ($this->activePhoto['image'])
                        <img
                            src="{{ asset('storage/'.$this->activePhoto['image']) }}"
                            alt="{{ $product->name }}"
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

            {{-- TODO(BE-064): sambungkan ke keranjang. Sekarang tombolnya baru memantulkan ketersediaan stok. --}}
            <button
                type="button"
                @disabled(! $this->isAvailable)
                class="mt-5 flex w-full cursor-pointer items-center justify-center gap-3 rounded-[2px] bg-ink px-8 py-4 text-[13.5px] font-semibold uppercase tracking-[0.04em] text-cream transition-opacity duration-200 hover:opacity-85 disabled:cursor-not-allowed disabled:bg-line disabled:text-muted disabled:hover:opacity-100"
            >
                @if ($this->isAvailable)
                    Add to Bag

                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14m0 0-6-6m6 6-6 6" />
                    </svg>
                @else
                    Sold Out
                @endif
            </button>

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
