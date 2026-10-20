<?php

use App\Models\CategoryType;
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

    /** `men` / `women`. Produk unisex ikut tampil di keduanya. */
    #[Url(as: 'gender')]
    public string $gender = '';

    /** Slug jenis kategori, mis. `aksesoris` atau `alas-kaki`. */
    #[Url(as: 'type')]
    public string $type = '';

    /** `new` (New Arrivals) atau `sale` (On Sale). */
    #[Url(as: 'collection')]
    public string $collection = '';

    /** Kata kunci dari kolom cari di navbar, mis. `kaos hitam`. */
    #[Url(as: 'q')]
    public string $search = '';

    /** Batas panjang kata kunci, supaya URL iseng tidak jadi query raksasa. */
    public const SEARCH_MAX_LENGTH = 100;

    /**
     * "New Arrivals" = sekian produk aktif terbaru. Sengaja bukan rentang tanggal,
     * supaya menunya tidak kosong saat toko lama tidak menambah produk.
     */
    public const NEW_ARRIVAL_COUNT = 12;

    public function mount(): void
    {
        // Semua filter ini bisa diisi sembarangan lewat URL, jadi dikembalikan ke
        // default kalau bukan salah satu pilihan yang memang disediakan.
        if (! array_key_exists($this->sort, $this->sortOptions)) {
            $this->sort = 'newest';
        }

        if (! array_key_exists($this->gender, $this->genderOptions)) {
            $this->gender = '';
        }

        if (! $this->categoryTypes->contains('slug', $this->type)) {
            $this->type = '';
        }

        if (! array_key_exists($this->collection, $this->collectionOptions)) {
            $this->collection = '';
        }

        $this->search = mb_substr(trim($this->search), 0, self::SEARCH_MAX_LENGTH);
    }

    public function clearSearch(): void
    {
        $this->search = '';

        unset($this->searchTerms);
        $this->resetPage();
    }

    /**
     * Memilih atau (kalau diklik lagi) melepas satu filter pilihan tunggal.
     */
    public function setFilter(string $filter, string $value): void
    {
        $options = match ($filter) {
            'gender' => array_keys($this->genderOptions),
            'type' => $this->categoryTypes->pluck('slug')->all(),
            'collection' => array_keys($this->collectionOptions),
            default => [],
        };

        if (! in_array($value, $options, true)) {
            return;
        }

        $this->{$filter} = $this->{$filter} === $value ? '' : $value;

        $this->resetPage();
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
        $this->reset(['sizeIds', 'colorIds', 'minPrice', 'maxPrice', 'gender', 'type', 'collection', 'search']);

        unset($this->selectedSizeIds, $this->selectedColorIds, $this->searchTerms);
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
            ->with(['category', 'images'])
            ->when($this->searchTerms, fn (Builder $query): Builder => $query->where(function (Builder $matches): void {
                foreach ($this->searchTerms as $term) {
                    $matches->where(fn (Builder $word): Builder => $word
                        ->where('name', 'like', '%'.$term.'%')
                        ->orWhereHas('category', fn (Builder $category): Builder => $category->where('name', 'like', '%'.$term.'%'))
                        ->orWhereHas('category.categoryType', fn (Builder $categoryType): Builder => $categoryType->where('name', 'like', '%'.$term.'%')));
                }
            }))
            ->when($this->gender !== '', fn (Builder $query): Builder => $query->whereIn('gender', [$this->gender, 'unisex']))
            ->when($this->type !== '', fn (Builder $query): Builder => $query->whereHas(
                'category.categoryType',
                fn (Builder $categoryType): Builder => $categoryType->where('slug', $this->type)
            ))
            ->when($this->collection === 'new', fn (Builder $query): Builder => $query->whereIn('id', $this->newArrivalIds))
            ->when($this->collection === 'sale', fn (Builder $query): Builder => $query->whereColumn('compare_at_price', '>', 'base_price'))
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
            ->when($this->type !== '', fn (Builder $query): Builder => $query->whereHas(
                'categoryType',
                fn (Builder $categoryType): Builder => $categoryType->where('slug', $this->type)
            ))
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
     * Kata kunci dipecah per kata. Setiap kata cukup muncul di nama produk, nama
     * kategori, atau jenis kategorinya, jadi "kaos basic" tetap menemukan
     * "Kaos Katun Basic" dan "sepatu" menemukan semua produk alas kaki.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function searchTerms(): array
    {
        return collect(preg_split('/\s+/u', trim($this->search), -1, PREG_SPLIT_NO_EMPTY))
            ->unique(fn (string $term): string => mb_strtolower($term))
            ->take(5)
            ->values()
            ->all();
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

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function genderOptions(): array
    {
        return [
            'men' => 'Men',
            'women' => 'Women',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function collectionOptions(): array
    {
        return [
            'new' => 'New Arrivals',
            'sale' => 'On Sale',
        ];
    }

    /**
     * Diambil terpisah karena MySQL tidak mendukung LIMIT di dalam subquery IN.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function newArrivalIds(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->latest()
            ->orderByDesc('id')
            ->limit(self::NEW_ARRIVAL_COUNT)
            ->pluck('id')
            ->all();
    }

    /**
     * Jenis kategori (atasan, bawahan, alas kaki, aksesoris) yang punya produk aktif.
     *
     * @return EloquentCollection<int, CategoryType>
     */
    #[Computed]
    public function categoryTypes(): EloquentCollection
    {
        return CategoryType::query()
            ->where('is_active', true)
            ->whereHas('categories.products', fn (Builder $query): Builder => $query->where('is_active', true))
            ->orderBy('id')
            ->get();
    }

    /**
     * Judul hasil sesuai menu yang sedang dipilih, mis. "Men · Aksesoris", atau
     * kata kunci pencarian di depan, mis. "“kaos” · Men".
     */
    #[Computed]
    public function heading(): string
    {
        return collect([
            $this->search !== '' ? '“'.$this->search.'”' : null,
            $this->collectionOptions[$this->collection] ?? null,
            $this->genderOptions[$this->gender] ?? null,
            $this->categoryTypes->firstWhere('slug', $this->type)?->name,
        ])->filter()->join(' · ') ?: 'All Products';
    }

    /**
     * Jejak navigasi: menu yang sedang dipilih (Men, On Sale, ...) jadi item terakhir.
     *
     * @return array<string, string|null>
     */
    #[Computed]
    public function breadcrumbs(): array
    {
        if ($this->heading === 'All Products') {
            return ['Beranda' => route('home'), 'Katalog' => null];
        }

        return [
            'Beranda' => route('home'),
            'Katalog' => route('products.index'),
            $this->heading => null,
        ];
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->gender !== ''
            || $this->type !== ''
            || $this->collection !== ''
            || $this->selectedSizeIds !== []
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
     * Bidang warna di belakang foto kartu: tampil selama foto dimuat, dan jadi
     * pengganti untuk produk yang belum punya foto. Dipetakan dari id supaya tiap
     * kartu konsisten antar halaman. Nama class gradiennya ditulis utuh supaya
     * tetap terpindai Tailwind.
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
    <x-ui.breadcrumbs :items="$this->breadcrumbs" />

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

            @php
                /** @var array<string, array{label: string, options: array<string, string>, selected: string}> Filter pilihan tunggal: nama properti => isinya. */
                $singleChoiceFilters = [
                    'collection' => ['label' => 'Shop', 'options' => $this->collectionOptions, 'selected' => $collection],
                    'gender' => ['label' => 'Gender', 'options' => $this->genderOptions, 'selected' => $gender],
                    'type' => ['label' => 'Category', 'options' => $this->categoryTypes->pluck('name', 'slug')->all(), 'selected' => $type],
                ];
            @endphp

            @foreach ($singleChoiceFilters as $filter => $group)
                @if ($group['options'] !== [])
                    <div wire:key="filter-{{ $filter }}" class="border-b border-line py-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">{{ $group['label'] }}</p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($group['options'] as $value => $label)
                                <button
                                    type="button"
                                    wire:key="filter-{{ $filter }}-{{ $value }}"
                                    wire:click="setFilter('{{ $filter }}', '{{ $value }}')"
                                    @class([
                                        'flex h-9 cursor-pointer items-center justify-center rounded-[2px] border px-3 text-[12.5px] font-medium transition-colors duration-150',
                                        'border-ink bg-ink text-cream' => $group['selected'] === $value,
                                        'border-line hover:border-ink' => $group['selected'] !== $value,
                                    ])
                                    aria-pressed="{{ $group['selected'] === $value ? 'true' : 'false' }}"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

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
                <div>
                    <h2 class="font-display text-[clamp(1.2rem,2.4vw,1.45rem)] font-normal leading-tight">{{ $this->heading }}</h2>
                    <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-muted">
                        Showing {{ $this->products->total() }} {{ $this->products->total() === 1 ? 'Result' : 'Results' }}

                        @if ($search !== '')
                            <button
                                type="button"
                                wire:click="clearSearch"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-line px-2.5 py-0.5 text-[12px] text-ink transition-colors duration-150 hover:border-ink"
                                aria-label="Hapus pencarian {{ $search }}"
                            >
                                {{ $search }}

                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                    <path d="M18 6 6 18M6 6l12 12" />
                                </svg>
                            </button>
                        @endif
                    </p>
                </div>

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

            {{--
                Selama filter, kategori, urutan, atau halaman baru dimuat, hasil diganti
                kerangka kartu. Dua pemicunya:
                - request Livewire komponen ini (`wire:loading`), dan
                - klik menu navbar yang menuju katalog lagi (`livewire:navigate` ke path
                  yang sama), karena itu navigasi halaman, bukan request komponen.
                Kerangka diberi class `hidden` supaya tetap tersembunyi sebelum JS siap;
                CSS bawaan Livewire tidak menyembunyikan `wire:loading.delay.grid`.
            --}}
            <div
                class="group/results"
                x-data="{ navigating: false }"
                x-on:livewire:navigate.window="navigating = $event.detail.url.pathname === window.location.pathname"
                x-bind:data-navigating="navigating ? 'true' : null"
                wire:loading.delay.shorter.attr="aria-busy"
            >
                <p wire:loading.delay.shorter class="sr-only" role="status">Memuat produk…</p>

                <div wire:loading.delay.shorter.remove class="group-data-navigating/results:hidden">
                    @if ($this->products->isEmpty())
                        <div class="flex flex-col items-center gap-4 py-[clamp(48px,10vw,96px)] text-center">
                            <svg class="size-[33px] text-line" viewBox="0 0 22 36" fill="none" aria-hidden="true">
                                <path d="M11 0 L22 18 L11 36 L0 18 Z" stroke="currentColor" stroke-width="1.2" />
                            </svg>

                            <p class="font-display text-[clamp(1.2rem,3vw,1.6rem)]">Tidak ada produk yang cocok</p>

                            <p class="max-w-[38ch] text-[13.5px] leading-[1.7] text-muted">
                                @if ($search !== '')
                                    Coba kata kunci lain, mis. nama kategori seperti &ldquo;kaos&rdquo; atau &ldquo;sepatu&rdquo;, atau longgarkan filternya.
                                @else
                                    Coba longgarkan filternya — kurangi pilihan ukuran, warna, atau lebarkan rentang harganya.
                                @endif
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

                <div
                    wire:loading.delay.shorter.grid
                    class="mt-[clamp(20px,3vw,28px)] hidden grid-cols-2 gap-x-5 gap-y-[clamp(24px,4vw,36px)] group-data-navigating/results:grid sm:grid-cols-3 xl:grid-cols-4"
                    aria-hidden="true"
                >
                    @foreach (range(1, 8) as $index)
                        <div wire:key="product-skeleton-{{ $index }}">
                            <x-ui.skeleton class="aspect-[4/5]" />
                            <x-ui.skeleton class="mt-3 h-3.5 w-3/4 rounded-sm" />
                            <x-ui.skeleton class="mt-2 h-3.5 w-1/3 rounded-sm" />
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
