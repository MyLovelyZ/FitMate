<?php

use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Catalog')] class extends Component
{
    use WithPagination;

    /**
     * Ukuran yang sedang dicentang. Nilainya datang dari query string juga, jadi
     * isinya belum tentu integer — pakai `selectedSizeIds` kalau butuh id bersih.
     *
     * @var array<int, int|string>
     */
    #[Url(as: 'size')]
    public array $sizeIds = [];

    /**
     * @var array<int, int|string>
     */
    #[Url(as: 'color')]
    public array $colorIds = [];

    /** Batas harga dibiarkan string supaya input kosong tetap valid. */
    #[Url(as: 'min')]
    public string $minPrice = '';

    #[Url(as: 'max')]
    public string $maxPrice = '';

    #[Url(as: 'sort')]
    public string $sort = 'newest';

    public function mount(): void
    {
        // `sort` bisa diisi sembarangan lewat URL, jadi dikembalikan ke default
        // kalau bukan salah satu pilihan yang memang disediakan.
        if (! array_key_exists($this->sort, $this->sortOptions)) {
            $this->sort = 'newest';
        }
    }

    public function toggleSize(int $sizeId): void
    {
        $this->sizeIds = $this->toggleId($this->selectedSizeIds, $sizeId);

        unset($this->selectedSizeIds);
        $this->resetPage();
    }

    public function toggleColor(int $colorId): void
    {
        $this->colorIds = $this->toggleId($this->selectedColorIds, $colorId);

        unset($this->selectedColorIds);
        $this->resetPage();
    }

    /**
     * Rentang harga sengaja baru dipakai setelah tombol "Apply Filters" ditekan,
     * supaya daftar produk tidak di-query ulang tiap ketikan.
     */
    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['sizeIds', 'colorIds', 'minPrice', 'maxPrice']);

        unset($this->selectedSizeIds, $this->selectedColorIds);
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    /**
     * Produk yang tampil di grid. Filter ukuran dan warna dicek pada varian yang
     * sama, jadi "S" + "Hitam" berarti produk itu benar-benar punya varian S hitam.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    #[Computed]
    public function products(): LengthAwarePaginator
    {
        return Product::query()
            ->where('is_active', true)
            ->with('category')
            ->when(
                $this->selectedSizeIds || $this->selectedColorIds,
                fn (Builder $query): Builder => $query->whereHas(
                    'variants',
                    fn (Builder $variants): Builder => $variants
                        ->where('is_active', true)
                        ->when($this->selectedSizeIds, fn (Builder $scoped): Builder => $scoped->whereIn('size_id', $this->selectedSizeIds))
                        ->when($this->selectedColorIds, fn (Builder $scoped): Builder => $scoped->whereIn('color_id', $this->selectedColorIds))
                )
            )
            ->when($this->minPrice !== '', fn (Builder $query): Builder => $query->where('base_price', '>=', (float) $this->minPrice))
            ->when($this->maxPrice !== '', fn (Builder $query): Builder => $query->where('base_price', '<=', (float) $this->maxPrice))
            ->tap(fn (Builder $query): Builder => match ($this->sort) {
                'price-asc' => $query->orderBy('base_price'),
                'price-desc' => $query->orderByDesc('base_price'),
                'name' => $query->orderBy('name'),
                default => $query->orderByDesc('id'),
            })
            ->paginate(12);
    }

    /**
     * Ukuran yang benar-benar dijual, dikelompokkan per jenis kategori (atasan,
     * bawahan, alas kaki) karena kodenya tidak sebanding antar jenis.
     *
     * @return Collection<string, EloquentCollection<int, Size>>
     */
    #[Computed]
    public function sizeGroups(): Collection
    {
        return Size::query()
            ->where('is_active', true)
            ->whereHas('productVariants', fn (Builder $query): Builder => $query
                ->where('is_active', true)
                ->whereHas('product', fn (Builder $product): Builder => $product->where('is_active', true)))
            ->with('categoryType')
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn (Size $size): string => $size->categoryType->name);
    }

    /**
     * @return EloquentCollection<int, Color>
     */
    #[Computed]
    public function colors(): EloquentCollection
    {
        return Color::query()
            ->where('is_active', true)
            ->whereHas('productVariants', fn (Builder $query): Builder => $query
                ->where('is_active', true)
                ->whereHas('product', fn (Builder $product): Builder => $product->where('is_active', true)))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function sortOptions(): array
    {
        return [
            'newest' => 'Newest',
            'price-asc' => 'Price: Low to High',
            'price-desc' => 'Price: High to Low',
            'name' => 'Name: A–Z',
        ];
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function selectedSizeIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->sizeIds)));
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function selectedColorIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->colorIds)));
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->selectedSizeIds !== []
            || $this->selectedColorIds !== []
            || $this->minPrice !== ''
            || $this->maxPrice !== '';
    }

    /**
     * Nomor halaman yang ditampilkan, dengan `null` sebagai penanda elipsis.
     *
     * @return array<int, ?int>
     */
    #[Computed]
    public function pageLinks(): array
    {
        $lastPage = $this->products->lastPage();

        if ($lastPage <= 5) {
            return range(1, max($lastPage, 1));
        }

        $currentPage = $this->products->currentPage();
        $windowStart = max(1, min($currentPage - 1, $lastPage - 2));
        $windowEnd = min($lastPage, max($currentPage + 1, 3));

        $pages = collect([1, $lastPage])
            ->merge(range($windowStart, $windowEnd))
            ->unique()
            ->sort()
            ->values();

        return $pages
            ->flatMap(fn (int $page, int $index): array => $index > 0 && $page - $pages[$index - 1] > 1
                ? [null, $page]
                : [$page])
            ->all();
    }

    /**
     * Bidang warna penahan tempat per produk, dipetakan dari id supaya tiap kartu
     * konsisten antar halaman. Tabel foto produk belum ada (BE-053), jadi katalog
     * memakai pola yang sama dengan halaman detail. Nama class gradiennya ditulis
     * utuh supaya tetap terpindai Tailwind.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function placeholderSurfaces(): array
    {
        $surfaces = [
            'bg-[linear-gradient(150deg,#cbc4b5,#948d7c)]',
            'bg-[linear-gradient(150deg,#d9d2c1,#b3ab98)]',
            'bg-[linear-gradient(150deg,#c3bcac,#8b8474)]',
            'bg-[linear-gradient(150deg,#e0dacd,#a8a091)]',
        ];

        return $this->products
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => $surfaces[$product->id % count($surfaces)],
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    protected function toggleId(array $ids, int $id): array
    {
        return in_array($id, $ids, true)
            ? array_values(array_diff($ids, [$id]))
            : [...$ids, $id];
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side py-[clamp(20px,4vw,32px)]">
    <a
        href="{{ route('home') }}"
        wire:navigate
        class="inline-flex items-center gap-2 text-[13px] text-muted transition-colors duration-150 hover:text-ink"
    >
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M19 12H5m0 0 6-6m-6 6 6 6" />
        </svg>
        Back
    </a>

    <div class="mt-[clamp(16px,3vw,28px)] grid grid-cols-1 items-start gap-[clamp(28px,5vw,56px)] lg:grid-cols-[minmax(0,200px)_minmax(0,1fr)]">
        {{-- Panel filter --}}
        <aside aria-label="Filter produk">
            <div class="flex items-baseline justify-between gap-4 border-b border-line pb-4">
                <h1 class="font-display text-[clamp(1.3rem,2.5vw,1.6rem)] font-normal">Filters</h1>

                @if ($this->hasActiveFilters)
                    <button
                        type="button"
                        wire:click="resetFilters"
                        class="cursor-pointer text-[12px] text-muted underline decoration-line underline-offset-4 transition-colors duration-150 hover:text-ink"
                    >
                        Reset
                    </button>
                @endif
            </div>

            @foreach ($this->sizeGroups as $groupName => $sizes)
                <div wire:key="size-group-{{ $loop->index }}" class="border-b border-line py-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">
                        Size @if ($this->sizeGroups->count() > 1)<span class="normal-case tracking-normal">&middot; {{ $groupName }}</span>@endif
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($sizes as $size)
                            <button
                                type="button"
                                wire:key="size-{{ $size->id }}"
                                wire:click="toggleSize({{ $size->id }})"
                                @class([
                                    'flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-[2px] border px-2.5 text-[12.5px] font-medium transition-colors duration-150',
                                    'border-ink bg-ink text-cream' => in_array($size->id, $this->selectedSizeIds, true),
                                    'border-line hover:border-ink' => ! in_array($size->id, $this->selectedSizeIds, true),
                                ])
                                aria-pressed="{{ in_array($size->id, $this->selectedSizeIds, true) ? 'true' : 'false' }}"
                                title="{{ $size->name }}"
                            >
                                {{ $size->code ?? $size->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($this->colors->isNotEmpty())
                <div class="border-b border-line py-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">Color</p>

                    <div class="mt-3 flex flex-wrap gap-2.5">
                        @foreach ($this->colors as $color)
                            <button
                                type="button"
                                wire:key="color-{{ $color->id }}"
                                wire:click="toggleColor({{ $color->id }})"
                                @class([
                                    'size-[26px] cursor-pointer rounded-full border transition-[box-shadow,border-color] duration-150',
                                    'border-ink shadow-[0_0_0_2px_var(--color-bg),0_0_0_3px_var(--color-ink)]' => in_array($color->id, $this->selectedColorIds, true),
                                    'border-line hover:border-muted' => ! in_array($color->id, $this->selectedColorIds, true),
                                ])
                                style="background-color: {{ $color->hex_code }}"
                                aria-pressed="{{ in_array($color->id, $this->selectedColorIds, true) ? 'true' : 'false' }}"
                                aria-label="{{ $color->name }}"
                                title="{{ $color->name }}"
                            ></button>
                        @endforeach
                    </div>
                </div>
            @endif

            <form wire:submit="applyFilters" class="py-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">Price Range</p>

                <div class="mt-3 flex items-center gap-2">
                    <input
                        type="number"
                        min="0"
                        step="1000"
                        wire:model="minPrice"
                        value="{{ $minPrice }}"
                        placeholder="Min"
                        aria-label="Harga minimum"
                        class="h-11 w-full min-w-0 rounded-[2px] border border-line bg-surface px-3 text-[13px] outline-none transition-colors duration-150 placeholder:text-muted focus:border-ink"
                    >

                    <span class="text-muted" aria-hidden="true">&ndash;</span>

                    <input
                        type="number"
                        min="0"
                        step="1000"
                        wire:model="maxPrice"
                        value="{{ $maxPrice }}"
                        placeholder="Max"
                        aria-label="Harga maksimum"
                        class="h-11 w-full min-w-0 rounded-[2px] border border-line bg-surface px-3 text-[13px] outline-none transition-colors duration-150 placeholder:text-muted focus:border-ink"
                    >
                </div>

                <button
                    type="submit"
                    class="mt-5 flex w-full cursor-pointer items-center justify-center rounded-[2px] bg-ink px-6 py-3.5 text-[12.5px] font-semibold uppercase tracking-[0.04em] text-cream transition-opacity duration-200 hover:opacity-85"
                >
                    Apply Filters
                </button>
            </form>
        </aside>

        {{-- Hasil --}}
        <div>
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-line pb-4">
                <p class="text-[13px] text-muted">
                    Showing {{ $this->products->total() }} {{ $this->products->total() === 1 ? 'Result' : 'Results' }}
                </p>

                <div class="flex items-center gap-2.5">
                    <label for="sort" class="text-[13px] text-muted">Sort By:</label>

                    <div class="relative">
                        <select
                            id="sort"
                            wire:model.live="sort"
                            class="cursor-pointer appearance-none rounded-[2px] bg-transparent py-1 pl-1 pr-7 text-[13px] font-medium outline-none focus:underline focus:decoration-line focus:underline-offset-4"
                        >
                            @foreach ($this->sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <svg class="pointer-events-none absolute right-1 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </div>
                </div>
            </div>

            @if ($this->products->isEmpty())
                <div class="flex flex-col items-center gap-4 py-[clamp(48px,10vw,96px)] text-center">
                    <svg class="size-[33px] text-line" viewBox="0 0 22 36" fill="none" aria-hidden="true">
                        <path d="M11 0 L22 18 L11 36 L0 18 Z" stroke="currentColor" stroke-width="1.2" />
                    </svg>

                    <p class="font-display text-[clamp(1.2rem,3vw,1.6rem)]">Tidak ada produk yang cocok</p>

                    <p class="max-w-[38ch] text-[13.5px] leading-[1.7] text-muted">
                        Coba longgarkan filternya — kurangi pilihan ukuran, warna, atau lebarkan rentang harganya.
                    </p>

                    @if ($this->hasActiveFilters)
                        <button
                            type="button"
                            wire:click="resetFilters"
                            class="mt-1 cursor-pointer rounded-[2px] border border-line px-6 py-3 text-[12.5px] font-semibold uppercase tracking-[0.04em] transition-colors duration-150 hover:border-ink"
                        >
                            Hapus Filter
                        </button>
                    @endif
                </div>
            @else
                <div class="mt-[clamp(20px,3vw,28px)] grid grid-cols-2 gap-x-5 gap-y-[clamp(24px,4vw,36px)] sm:grid-cols-3 xl:grid-cols-4">
                    @foreach ($this->products as $product)
                        <a
                            wire:key="product-{{ $product->id }}"
                            href="{{ route('products.show', $product) }}"
                            wire:navigate
                            class="group flex flex-col"
                        >
                            <div class="relative aspect-[4/5] overflow-hidden rounded-md border border-line {{ $this->placeholderSurfaces[$product->id] }}">
                                <span class="absolute bottom-3 left-3 rounded-[3px] bg-cream/92 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-ink opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                                    {{ $product->category->name }}
                                </span>
                            </div>

                            <p class="mt-3 text-[13.5px] leading-[1.45] transition-colors duration-150 group-hover:text-muted">
                                {{ $product->name }}
                            </p>

                            <p class="mt-1.5 text-[13.5px] font-medium">
                                Rp {{ number_format((float) $product->base_price, 0, ',', '.') }}
                            </p>
                        </a>
                    @endforeach
                </div>

                @if ($this->products->hasPages())
                    <nav class="mt-[clamp(32px,5vw,48px)] flex items-center justify-center gap-2 border-t border-line pt-[clamp(20px,3vw,28px)]" aria-label="Halaman">
                        <button
                            type="button"
                            wire:click="previousPage"
                            @disabled($this->products->onFirstPage())
                            class="flex size-9 cursor-pointer items-center justify-center rounded-[2px] border border-line transition-colors duration-150 hover:border-ink disabled:cursor-not-allowed disabled:text-muted disabled:hover:border-line"
                            aria-label="Halaman sebelumnya"
                        >
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m15 18-6-6 6-6" />
                            </svg>
                        </button>

                        @foreach ($this->pageLinks as $page)
                            @if ($page === null)
                                <span wire:key="gap-{{ $loop->index }}" class="px-1 text-[13px] text-muted" aria-hidden="true">&hellip;</span>
                            @else
                                <button
                                    type="button"
                                    wire:key="page-{{ $page }}"
                                    wire:click="gotoPage({{ $page }})"
                                    @class([
                                        'flex size-9 cursor-pointer items-center justify-center rounded-[2px] border text-[13px] transition-colors duration-150',
                                        'border-ink bg-ink text-cream' => $this->products->currentPage() === $page,
                                        'border-line hover:border-ink' => $this->products->currentPage() !== $page,
                                    ])
                                    @if ($this->products->currentPage() === $page) aria-current="page" @endif
                                >
                                    {{ $page }}
                                </button>
                            @endif
                        @endforeach

                        <button
                            type="button"
                            wire:click="nextPage"
                            @disabled(! $this->products->hasMorePages())
                            class="flex size-9 cursor-pointer items-center justify-center rounded-[2px] border border-line transition-colors duration-150 hover:border-ink disabled:cursor-not-allowed disabled:text-muted disabled:hover:border-line"
                            aria-label="Halaman berikutnya"
                        >
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m9 18 6-6-6-6" />
                            </svg>
                        </button>
                    </nav>
                @endif
            @endif
        </div>
    </div>
</div>
