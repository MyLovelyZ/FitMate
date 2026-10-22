<?php

use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    /**
     * @var array<string, string>
     */
    public const SORT_OPTIONS = [
        'newest' => 'Terbaru',
        'price-asc' => 'Harga Terendah',
        'price-desc' => 'Harga Tertinggi',
        'name' => 'Nama A–Z',
    ];

    public Store $store;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public function mount(Store $store): void
    {
        // Publik hanya melihat toko yang sedang berjualan. Pemilik dan admin tetap
        // bisa membuka halamannya untuk pratinjau.
        abort_unless($store->canSell() || $this->canPreview($store), 404);

        $this->store = $store;

        if (! array_key_exists($this->sort, self::SORT_OPTIONS)) {
            $this->sort = 'newest';
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    #[Computed]
    public function products(): LengthAwarePaginator
    {
        return $this->store->products()
            ->where('is_active', true)
            ->with(['category', 'images'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->tap(fn ($query) => match ($this->sort) {
                'price-asc' => $query->orderBy('base_price'),
                'price-desc' => $query->orderByDesc('base_price'),
                'name' => $query->orderBy('name'),
                default => $query->latest()->orderByDesc('id'),
            })
            ->paginate(12);
    }

    #[Computed]
    public function productCount(): int
    {
        return $this->store->products()->where('is_active', true)->count();
    }

    #[Computed]
    public function isPreview(): bool
    {
        return ! $this->store->canSell();
    }

    public function render(): View
    {
        return $this->view()->title($this->store->name);
    }

    protected function canPreview(Store $store): bool
    {
        $user = Auth::user();

        return $user !== null && ($store->user_id === $user->id || in_array($user->role, ['admin', 'superadmin'], true));
    }
};
?>

<div class="pb-[clamp(48px,8vw,80px)]">
    @if ($this->isPreview)
        <div class="bg-sale/10 px-side py-2.5 text-center text-[12.5px] text-sale" role="status">
            Pratinjau — toko ini belum tampil untuk publik (status: {{ $store->statusLabel() }}).
        </div>
    @endif

    {{-- Banner --}}
    <div class="relative h-[clamp(140px,22vw,240px)] overflow-hidden bg-[linear-gradient(120deg,#d8d2c4,#b9b2a0_45%,#8f8a7c)]">
        @if ($store->banner)
            <img src="{{ asset('storage/'.$store->banner) }}" alt="" class="absolute inset-0 size-full object-cover">
        @endif
    </div>

    <div class="mx-auto max-w-[1280px] px-side">
        <x-ui.breadcrumbs
            class="pt-4"
            :items="['Beranda' => route('home'), 'Katalog' => route('products.index'), $store->name => null]"
        />

        <header class="mt-4 flex flex-col gap-5 border-b border-line pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-end gap-4">
                <span class="-mt-14 flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-full border-4 border-bg bg-cream font-display text-4xl shadow-[0_4px_16px_rgba(10,25,47,0.12)]">
                    @if ($store->logoUrl())
                        <img src="{{ $store->logoUrl() }}" alt="Logo {{ $store->name }}" class="size-full object-cover">
                    @else
                        {{ str($store->name)->substr(0, 1)->upper() }}
                    @endif
                </span>

                <div class="min-w-0">
                    <h1 class="font-display text-[clamp(1.6rem,3.6vw,2.4rem)] font-normal leading-tight">{{ $store->name }}</h1>
                    <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[12.5px] text-muted">
                        @if ($store->city)
                            <span>{{ $store->city }}{{ $store->province ? ', '.$store->province : '' }}</span>
                        @endif
                        @if ($store->verified_at)
                            <span>Bergabung {{ $store->verified_at->format('M Y') }}</span>
                        @endif
                        @if ($store->rating_count > 0)
                            <span>&#9733; {{ number_format((float) $store->rating_average, 1) }} ({{ $store->rating_count }} ulasan)</span>
                        @endif
                    </p>
                </div>
            </div>

            <dl class="flex gap-6 text-[12.5px]">
                <div>
                    <dt class="text-muted">Produk</dt>
                    <dd class="font-display text-xl">{{ $this->productCount }}</dd>
                </div>
                @if ($store->verified_at)
                    <div>
                        <dt class="text-muted">Status</dt>
                        <dd class="mt-1 inline-flex items-center gap-1.5 font-semibold text-success">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m5 12.5 4.5 4.5L19 7.5" />
                            </svg>
                            Terverifikasi
                        </dd>
                    </div>
                @endif
            </dl>
        </header>

        @if ($store->description)
            <p class="mt-5 max-w-[72ch] whitespace-pre-line text-[13.5px] leading-[1.75] text-muted">{{ $store->description }}</p>
        @endif

        {{-- Produk toko --}}
        <section class="mt-8">
            <div class="flex flex-col gap-3 border-b border-line pb-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-[13px] font-semibold uppercase tracking-[0.1em]">Produk dari {{ $store->name }}</h2>

                <div class="flex gap-2">
                    <label for="store-search" class="sr-only">Cari di toko ini</label>
                    <input
                        id="store-search"
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        value="{{ $search }}"
                        placeholder="Cari di toko ini…"
                        class="w-full rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px] sm:w-56"
                    >

                    <label for="store-sort" class="sr-only">Urutkan</label>
                    <select id="store-sort" wire:model.live="sort" class="rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px]">
                        @foreach ($this::SORT_OPTIONS as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div wire:loading.delay.shorter.remove>
                @if ($this->products->isEmpty())
                    <p class="py-16 text-center text-[13.5px] text-muted">
                        {{ $search !== '' ? 'Tidak ada produk yang cocok dengan "'.$search.'".' : 'Toko ini belum punya produk.' }}
                    </p>
                @else
                    <div class="mt-[clamp(20px,3vw,28px)] grid grid-cols-2 gap-x-5 gap-y-[clamp(24px,4vw,36px)] sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($this->products as $product)
                            <x-ui.product-card :product="$product" wire:key="store-product-{{ $product->id }}" />
                        @endforeach
                    </div>

                    <x-ui.pager :paginator="$this->products" />
                @endif
            </div>

            <div
                wire:loading.delay.shorter.grid
                class="mt-[clamp(20px,3vw,28px)] hidden grid-cols-2 gap-x-5 gap-y-[clamp(24px,4vw,36px)] sm:grid-cols-3 xl:grid-cols-4"
                aria-hidden="true"
            >
                @foreach (range(1, 8) as $index)
                    <div wire:key="store-skeleton-{{ $index }}">
                        <x-ui.skeleton class="aspect-[4/5]" />
                        <x-ui.skeleton class="mt-3 h-3.5 w-3/4 rounded-sm" />
                        <x-ui.skeleton class="mt-2 h-3.5 w-1/3 rounded-sm" />
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
