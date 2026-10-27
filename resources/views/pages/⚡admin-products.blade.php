<?php

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Semua barang yang dijual di FitMate dari semua toko, termasuk yang tidak
 * tampil di katalog. Admin dan superadmin bisa memblokir (menurunkan dari
 * katalog tanpa bisa ditayangkan lagi oleh seller), menghapus, dan memulihkan produk.
 */
new #[Title('Produk')] class extends Component
{
    use WithPagination;

    /**
     * Filter status: nilai query string => label.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'semua' => 'Semua status',
        'tayang' => 'Tayang di katalog',
        'nonaktif' => 'Dinonaktifkan seller',
        'diblokir' => 'Diblokir',
        'dihapus' => 'Dihapus',
    ];

    /**
     * Warna lencana status: nada => class utuh.
     *
     * @var array<string, string>
     */
    public const STATUS_TONES = [
        'success' => 'bg-success/12 text-success',
        'sale' => 'bg-sale/10 text-sale',
        'neutral' => 'bg-cream',
        'muted' => 'border border-line text-muted',
    ];

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'semua';

    /** Batasi ke satu toko, mis. dari halaman detail pengguna. */
    #[Url(as: 'toko')]
    public ?int $storeId = null;

    /** Produk yang sedang diisi alasan blokirnya. */
    public ?int $banningId = null;

    public string $banReason = '';

    public function mount(): void
    {
        if (! array_key_exists($this->status, self::STATUSES)) {
            $this->status = 'semua';
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedStoreId(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function store(): ?Store
    {
        return $this->storeId ? Store::withTrashed()->with('owner')->find($this->storeId) : null;
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    #[Computed]
    public function products(): LengthAwarePaginator
    {
        return Product::query()
            ->with(['store.owner', 'category', 'primaryImage'])
            ->withSum('variants', 'stock')
            ->when($this->status === 'dihapus', fn ($query) => $query->onlyTrashed())
            ->when($this->status === 'tayang', fn ($query) => $query->purchasable())
            ->when($this->status === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->when($this->status === 'diblokir', fn ($query) => $query->whereNotNull('banned_at'))
            ->when($this->storeId, fn ($query) => $query->where('store_id', $this->storeId))
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhereHas('store', fn ($store) => $store->where('name', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('store.owner', fn ($owner) => $owner
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%'));
            }))
            ->latest()
            ->paginate(20);
    }

    /**
     * Status yang dilihat admin, dari yang paling menentukan.
     *
     * @return array{0: string, 1: string} [label, nada di STATUS_TONES]
     */
    public function statusOf(Product $product): array
    {
        return match (true) {
            $product->trashed() => ['Dihapus', 'muted'],
            $product->isBanned() => ['Diblokir', 'sale'],
            ! $product->is_active => ['Dinonaktifkan seller', 'neutral'],
            ! $product->store?->canSell() => ['Toko tidak berjualan', 'neutral'],
            default => ['Tayang', 'success'],
        };
    }

    public function startBan(int $productId): void
    {
        $this->banningId = $productId;
        $this->banReason = '';
        $this->resetErrorBag();
    }

    public function cancelBan(): void
    {
        $this->reset('banningId', 'banReason');
        $this->resetErrorBag();
    }

    public function ban(): void
    {
        $this->validate(['banReason' => ['required', 'string', 'min:10', 'max:500']], attributes: ['banReason' => 'reason']);

        $product = Product::findOrFail($this->banningId);
        $product->ban($this->moderator(), $this->banReason);

        $this->reset('banningId', 'banReason');
        $this->dispatch('notify', message: "{$product->name} diblokir dan hilang dari katalog.");
    }

    public function unban(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $this->moderator();
        $product->unban();

        $this->dispatch('notify', message: "Blokir {$product->name} dibuka.");
    }

    /**
     * Soft delete: baris produk tetap ada supaya riwayat pesanan tidak rusak.
     */
    public function delete(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $this->moderator();
        $product->delete();

        $this->dispatch('notify', message: "{$product->name} dihapus. Riwayat pesanannya tetap utuh.");
    }

    public function restore(int $productId): void
    {
        $product = Product::onlyTrashed()->findOrFail($productId);
        $this->moderator();
        $product->restore();

        $this->dispatch('notify', message: "{$product->name} dipulihkan.");
    }

    /**
     * Pengaman tambahan selain middleware route: aksi ini hanya untuk admin dan superadmin.
     */
    private function moderator(): User
    {
        $user = Auth::user();

        abort_unless(in_array($user?->role, ['admin', 'superadmin'], true), 403);

        return $user;
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Admin' => route('admin.dashboard'), 'Produk' => null]"
        title="Produk"
        description="Semua barang yang dijual di FitMate, termasuk yang tidak tampil di katalog."
        :links="auth()->user()->adminAreaLinks()"
    />

    @if ($this->store)
        <p class="mt-6 flex flex-wrap items-center gap-2 text-[12.5px]">
            <span class="text-muted">Toko:</span>
            <span class="rounded-[3px] bg-cream px-2 py-0.5 font-semibold">{{ $this->store->displayName() }}</span>
            <span class="text-muted">milik</span>
            <a href="{{ route('admin.users.show', $this->store->user_id) }}" wire:navigate class="font-semibold underline-offset-4 hover:underline">{{ $this->store->owner?->name }}</a>
            <button type="button" wire:click="$set('storeId', null)" class="cursor-pointer text-muted underline underline-offset-4 hover:text-ink">Semua toko</button>
        </p>
    @endif

    <div class="mt-6 flex flex-col gap-2 sm:flex-row">
        <label for="search" class="sr-only">Cari produk</label>
        <input
            id="search"
            type="search"
            wire:model.live.debounce.400ms="search"
            value="{{ $search }}"
            placeholder="Cari produk, toko, atau pemilik…"
            class="w-full max-w-sm rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px]"
        >

        <label for="status" class="sr-only">Filter status</label>
        <select id="status" wire:model.live="status" class="rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px]">
            @foreach ($this::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @if ($this->products->isEmpty())
        <p class="mt-4 rounded-md border border-line bg-surface px-[18px] py-6 text-center text-[13px] text-muted">Tidak ada produk yang cocok.</p>
    @else
        <div wire:loading.delay.class="pointer-events-none opacity-50" class="mt-4 overflow-x-auto rounded-md border border-line bg-surface transition-opacity duration-200">
            <table class="w-full min-w-[920px] text-left text-[12.5px]">
                <thead class="text-[11px] uppercase tracking-[0.1em] text-muted">
                    <tr>
                        <th class="px-[18px] py-3 font-semibold">Produk</th>
                        <th class="px-[18px] py-3 font-semibold">Toko &amp; Pemilik</th>
                        <th class="px-[18px] py-3 font-semibold">Harga</th>
                        <th class="px-[18px] py-3 font-semibold">Stok</th>
                        <th class="px-[18px] py-3 font-semibold">Status</th>
                        <th class="px-[18px] py-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->products as $product)
                        @php([$statusLabel, $statusTone] = $this->statusOf($product))
                        <tr wire:key="product-{{ $product->id }}" class="border-t border-line align-top">
                            <td class="px-[18px] py-3">
                                <div class="flex items-center gap-3">
                                    <x-ui.product-thumb :product="$product" class="h-12 w-[38px] rounded-[2px]" />
                                    <div class="min-w-0">
                                        @if ($product->trashed())
                                            <p class="font-medium">{{ $product->name }}</p>
                                        @else
                                            <a href="{{ route('products.show', $product) }}" wire:navigate class="font-medium hover:underline">{{ $product->name }}</a>
                                        @endif
                                        <p class="text-[11.5px] text-muted">{{ $product->category?->name }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-[18px] py-3">
                                <button type="button" wire:click="$set('storeId', {{ $product->store_id }})" class="cursor-pointer text-left font-medium hover:underline">{{ $product->store?->displayName() ?? 'Toko dihapus' }}</button>
                                @if ($owner = $product->store?->owner)
                                    <a href="{{ route('admin.users.show', $owner) }}" wire:navigate class="block text-[11.5px] text-muted hover:text-ink hover:underline">{{ $owner->name }}{{ $owner->isBanned() ? ' · diblokir' : '' }}</a>
                                @endif
                            </td>
                            <td class="px-[18px] py-3">Rp {{ number_format((float) $product->base_price, 0, ',', '.') }}</td>
                            <td class="px-[18px] py-3">{{ (int) $product->variants_sum_stock }}</td>
                            <td class="px-[18px] py-3">
                                <span @class(['whitespace-nowrap rounded-[3px] px-2 py-0.5 text-[11px] font-semibold', $this::STATUS_TONES[$statusTone]])>{{ $statusLabel }}</span>
                                @if ($product->isBanned())
                                    <p class="mt-1 max-w-[220px] text-[11.5px] text-muted">{{ $product->ban_reason }}</p>
                                @endif
                            </td>
                            <td class="px-[18px] py-3">
                                <div class="flex flex-wrap justify-end gap-x-3 gap-y-1 text-[12px] font-semibold">
                                    @if ($product->trashed())
                                        <button type="button" wire:click="restore({{ $product->id }})" class="cursor-pointer underline-offset-4 hover:underline">Pulihkan</button>
                                    @else
                                        @if ($product->isBanned())
                                            <button type="button" wire:click="unban({{ $product->id }})" wire:confirm="Buka blokir {{ $product->name }}?" class="cursor-pointer underline-offset-4 hover:underline">Buka Blokir</button>
                                        @else
                                            <button type="button" wire:click="startBan({{ $product->id }})" class="cursor-pointer text-sale underline-offset-4 hover:underline">Blokir</button>
                                        @endif
                                        <button type="button" wire:click="delete({{ $product->id }})" wire:confirm="Hapus {{ $product->name }}? Produk hilang dari toko dan katalog, riwayat pesanannya tetap ada." class="cursor-pointer text-sale underline-offset-4 hover:underline">Hapus</button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        @if ($banningId === $product->id)
                            <tr wire:key="ban-{{ $product->id }}" class="bg-cream/60">
                                <td colspan="6" class="px-[18px] py-4">
                                    <form wire:submit="ban" class="flex flex-col gap-2.5" novalidate>
                                        <label for="banReason" class="text-[12px] font-semibold">Alasan memblokir {{ $product->name }}</label>
                                        <textarea
                                            id="banReason"
                                            wire:model="banReason"
                                            rows="2"
                                            maxlength="500"
                                            placeholder="Mis. barang palsu, foto menyesatkan, atau melanggar aturan FitMate. Ditampilkan ke seller."
                                            @class([
                                                'w-full max-w-2xl rounded-[2px] border bg-surface px-3 py-2 text-[12.5px] outline-none focus:border-ink',
                                                'border-sale' => $errors->has('banReason'),
                                                'border-line' => ! $errors->has('banReason'),
                                            ])
                                        >{{ $banReason }}</textarea>
                                        @error('banReason') <p class="text-[12px] text-sale">{{ $message }}</p> @enderror

                                        <div class="flex gap-2">
                                            <button type="submit" class="cursor-pointer rounded-[2px] bg-sale px-4 py-2 text-[11px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity hover:opacity-85">Blokir Produk</button>
                                            <button type="button" wire:click="cancelBan" class="cursor-pointer rounded-[2px] border border-line px-4 py-2 text-[11px] font-bold uppercase tracking-[0.1em] transition-colors hover:border-ink">Batal</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pager :paginator="$this->products" />
    @endif
</div>
