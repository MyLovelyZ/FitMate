<?php

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Satu akun dari sudut pandang admin/superadmin: data akun, toko, barang yang
 * dijualnya, serta tombol blokir dan hapus permanen. Admin hanya bisa membuka
 * pelanggan dan seller; ubah role khusus superadmin.
 */
new class extends Component
{
    /** Berapa produk terbaru yang ditampilkan; sisanya di halaman Produk. */
    public const PRODUCT_PREVIEW_LIMIT = 8;

    public User $user;

    public string $banReason = '';

    /** Role pilihan superadmin di form "Role Akun". */
    public string $role = '';

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->canView($user), 403);

        $this->user = $user;
        $this->role = $user->role;
    }

    /**
     * Toko yang sudah dihapus tetap ditampilkan: produknya masih bisa diperiksa.
     */
    #[Computed]
    public function store(): ?Store
    {
        return Store::withTrashed()->whereBelongsTo($this->user, 'owner')->first();
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        if ($this->store === null) {
            return new Collection;
        }

        return $this->store->products()
            ->withTrashed()
            ->with(['category', 'primaryImage', 'store'])
            ->withSum('variants', 'stock')
            ->latest()
            ->limit(self::PRODUCT_PREVIEW_LIMIT)
            ->get();
    }

    #[Computed]
    public function productCount(): int
    {
        return $this->store?->products()->withTrashed()->count() ?? 0;
    }

    #[Computed]
    public function orderCount(): int
    {
        return $this->user->orders()->count();
    }

    #[Computed]
    public function canModerate(): bool
    {
        return Auth::user()->canModerate($this->user);
    }

    #[Computed]
    public function deletionBlocker(): ?string
    {
        return $this->user->deletionBlocker();
    }

    #[Computed]
    public function canChangeRole(): bool
    {
        return Auth::user()->role === 'superadmin';
    }

    public function changeRole(): void
    {
        abort_unless($this->canChangeRole, 403);

        $this->validate(['role' => ['required', Rule::in(User::ASSIGNABLE_ROLES)]]);

        if ($blocker = $this->user->roleChangeBlocker()) {
            $this->addError('role', $blocker);

            return;
        }

        $this->user->changeRole($this->role);

        $this->dispatch('notify', message: "{$this->user->name} sekarang ".User::ROLE_LABELS[$this->role].'.');
    }

    public function ban(): void
    {
        abort_unless($this->canModerate && ! $this->user->isBanned(), 403);

        $this->validate(['banReason' => ['required', 'string', 'min:10', 'max:500']], attributes: ['banReason' => 'reason']);

        $this->user->ban(Auth::user(), $this->banReason);
        $this->reset('banReason');
        unset($this->store);

        $this->dispatch('notify', message: "{$this->user->name} diblokir dan langsung dikeluarkan dari sesinya.");
    }

    public function unban(): void
    {
        abort_unless($this->canModerate && $this->user->isBanned(), 403);

        $this->user->unban();

        $this->dispatch('notify', message: "Blokir {$this->user->name} dibuka.");
    }

    public function deleteAccount(): void
    {
        abort_unless($this->canModerate, 403);

        if ($this->deletionBlocker !== null) {
            return;
        }

        $name = $this->user->name;
        $this->user->deleteAccount();

        $this->dispatch('notify', message: "Akun {$name} dihapus permanen.");
        $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function render(): View
    {
        return $this->view()->title('Pengguna · '.$this->user->name);
    }
};
?>

<div class="mx-auto max-w-[1120px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.breadcrumbs :items="[
        'Beranda' => route('home'),
        'Admin' => route('admin.dashboard'),
        'Pengguna' => route('admin.users.index'),
        $user->name => null,
    ]" />

    <header class="mt-4 flex flex-wrap items-end justify-between gap-4 border-b border-line pb-5">
        <div class="flex items-center gap-4">
            <x-ui.avatar :user="$user" class="size-16 text-xl" />
            <div>
                <h1 class="font-display text-[clamp(1.5rem,3.2vw,2rem)] font-normal leading-tight">{{ $user->name }}</h1>
                <p class="mt-1 text-[12.5px] text-muted">{{ $user->email }} &middot; {{ User::ROLE_LABELS[$user->role] ?? $user->role }}</p>
            </div>
        </div>

        <span @class([
            'rounded-[3px] px-3 py-1 text-[12px] font-semibold',
            'bg-sale/10 text-sale' => $user->isBanned(),
            'bg-success/12 text-success' => ! $user->isBanned(),
        ])>{{ $user->isBanned() ? 'Diblokir' : 'Aktif' }}</span>
    </header>

    @if ($user->isBanned())
        <p class="mt-6 rounded-md border border-sale/30 bg-sale/5 px-[18px] py-3.5 text-[13px]" role="alert">
            <span class="font-semibold text-sale">Diblokir {{ $user->banned_at->format('d M Y, H:i') }}{{ $user->bannedBy ? ' oleh '.$user->bannedBy->name : '' }}.</span>
            <span class="text-muted">Alasan: <span class="text-ink">{{ $user->ban_reason }}</span></span>
        </p>
    @endif

    <div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex flex-col gap-4">
            <section class="rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-5">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Akun</h2>

                <dl class="mt-3 grid gap-x-6 gap-y-3 text-[12.5px] sm:grid-cols-2">
                    <div><dt class="text-muted">No. HP</dt><dd class="font-medium">{{ $user->phone_number ?: '—' }}</dd></div>
                    <div><dt class="text-muted">Bergabung</dt><dd class="font-medium">{{ $user->created_at?->format('d M Y') }}</dd></div>
                    <div><dt class="text-muted">Pesanan sebagai pembeli</dt><dd class="font-medium">{{ number_format($this->orderCount, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-muted">Produk dijual</dt><dd class="font-medium">{{ number_format($this->productCount, 0, ',', '.') }}</dd></div>
                </dl>
            </section>

            <section class="rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-5">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Toko</h2>

                @if ($store = $this->store)
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-[14px] font-semibold">{{ $store->displayName() }}</p>
                            <p class="mt-0.5 text-[12px] text-muted">
                                {{ $store->trashed() ? 'Dihapus' : $store->statusLabel() }}{{ $store->city ? ' · '.$store->city : '' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-[12.5px] font-semibold">
                            @unless ($store->trashed())
                                <a href="{{ route('admin.stores.show', $store) }}" wire:navigate class="underline-offset-4 hover:underline">Review toko</a>
                            @endunless
                            @if ($store->canSell())
                                <a href="{{ route('stores.show', $store) }}" wire:navigate class="underline-offset-4 hover:underline">Halaman toko</a>
                            @endif
                        </div>
                    </div>
                    @if ($store->status === 'suspended' && $store->review_note)
                        <p class="mt-3 text-[12px] text-muted">Catatan penangguhan: <span class="text-ink">{{ $store->review_note }}</span></p>
                    @endif
                @else
                    <p class="mt-2 text-[12.5px] text-muted">Akun ini tidak punya toko.</p>
                @endif
            </section>

            <section class="rounded-md border border-line bg-surface">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-[clamp(18px,3vw,28px)] py-4">
                    <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Barang yang Dijual</h2>
                    @if ($this->productCount > 0)
                        <a href="{{ route('admin.products.index', ['toko' => $this->store->id]) }}" wire:navigate class="text-[12.5px] font-semibold underline-offset-4 hover:underline">
                            Kelola semua ({{ $this->productCount }})
                        </a>
                    @endif
                </div>

                @if ($this->products->isEmpty())
                    <p class="px-[clamp(18px,3vw,28px)] py-5 text-[12.5px] text-muted">Belum ada barang yang dijual.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($this->products as $product)
                            <li wire:key="product-{{ $product->id }}" class="flex items-center gap-3 px-[clamp(18px,3vw,28px)] py-3">
                                <x-ui.product-thumb :product="$product" class="h-12 w-[38px] rounded-[2px]" />
                                <div class="min-w-0 flex-1">
                                    @if ($product->trashed())
                                        <p class="truncate text-[13px] font-medium">{{ $product->name }}</p>
                                    @else
                                        <a href="{{ route('products.show', $product) }}" wire:navigate class="block truncate text-[13px] font-medium hover:underline">{{ $product->name }}</a>
                                    @endif
                                    <p class="text-[11.5px] text-muted">
                                        Rp {{ number_format((float) $product->base_price, 0, ',', '.') }} &middot; stok {{ (int) $product->variants_sum_stock }}
                                    </p>
                                </div>
                                <span @class([
                                    'shrink-0 rounded-[3px] px-2 py-0.5 text-[11px] font-semibold',
                                    'border border-line text-muted' => $product->trashed(),
                                    'bg-sale/10 text-sale' => ! $product->trashed() && $product->isBanned(),
                                    'bg-success/12 text-success' => ! $product->trashed() && $product->isPurchasable(),
                                    'bg-cream' => ! $product->trashed() && ! $product->isBanned() && ! $product->isPurchasable(),
                                ])>
                                    {{ match (true) {
                                        $product->trashed() => 'Dihapus',
                                        $product->isBanned() => 'Diblokir',
                                        $product->isPurchasable() => 'Tayang',
                                        default => 'Tidak tayang',
                                    } }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- Tindakan --}}
        <aside class="flex flex-col gap-4 lg:sticky lg:top-[calc(var(--spacing-header)+16px)]">
            @if ($this->canChangeRole)
                <section class="rounded-md border border-line bg-surface px-[18px] py-5">
                    <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Role Akun</h2>

                    @if ($blocker = $user->roleChangeBlocker())
                        <p class="mt-2 text-[12.5px] text-muted">{{ User::ROLE_LABELS[$user->role] ?? $user->role }}. {{ $blocker }}</p>
                    @else
                        <p class="mt-2 text-[12.5px] text-muted">Naikkan pelanggan jadi Admin atau Customer Service, atau kembalikan jadi Pembeli.</p>

                        <form wire:submit="changeRole" class="mt-4" novalidate>
                            <label for="role" class="block text-[12px] font-semibold">Role</label>
                            <select
                                id="role"
                                wire:model="role"
                                @class([
                                    'mt-1.5 h-11 w-full rounded-[2px] border bg-bg px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                    'border-sale' => $errors->has('role'),
                                    'border-line' => ! $errors->has('role'),
                                ])
                            >
                                @foreach (User::ASSIGNABLE_ROLES as $assignable)
                                    <option value="{{ $assignable }}" @selected($role === $assignable)>{{ User::ROLE_LABELS[$assignable] }}</option>
                                @endforeach
                            </select>
                            @error('role') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror

                            <button
                                type="submit"
                                wire:confirm="Ubah role {{ $user->name }}? Akses akun ini langsung berubah."
                                class="mt-3 inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[2px] bg-ink px-5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity hover:opacity-85"
                            >
                                Ubah Role
                            </button>
                        </form>
                    @endif
                </section>
            @endif

            @if (! $this->canModerate)
                <section class="rounded-md border border-line bg-surface px-[18px] py-5 text-[12.5px] text-muted">
                    Akun superadmin tidak bisa diblokir atau dihapus dari sini.
                </section>
            @else
                <section class="rounded-md border border-line bg-surface px-[18px] py-5">
                    <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Blokir Akun</h2>

                    @if ($user->isBanned())
                        <p class="mt-2 text-[12.5px] text-muted">Membuka blokir membuat akun ini bisa masuk lagi. Toko yang ikut ditangguhkan tetap ditangguhkan sampai diaktifkan dari halaman review toko.</p>
                        <button
                            type="button"
                            wire:click="unban"
                            wire:confirm="Buka blokir {{ $user->name }}?"
                            class="mt-4 inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[2px] bg-ink px-5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity hover:opacity-85"
                        >
                            Buka Blokir
                        </button>
                    @else
                        <p class="mt-2 text-[12.5px] text-muted">Akun langsung dikeluarkan dan tidak bisa masuk lagi. Toko aktif miliknya ikut ditangguhkan sehingga barangnya hilang dari katalog.</p>

                        <form wire:submit="ban" class="mt-4" novalidate>
                            <label for="banReason" class="block text-[12px] font-semibold">Alasan</label>
                            <textarea
                                id="banReason"
                                wire:model="banReason"
                                rows="3"
                                maxlength="500"
                                placeholder="Ditampilkan ke pemilik akun saat mencoba masuk."
                                @class([
                                    'mt-1.5 w-full rounded-[2px] border bg-bg px-3 py-2.5 text-[13px] outline-none transition-colors placeholder:text-muted focus:border-ink',
                                    'border-sale' => $errors->has('banReason'),
                                    'border-line' => ! $errors->has('banReason'),
                                ])
                            >{{ $banReason }}</textarea>
                            @error('banReason') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror

                            <button
                                type="submit"
                                wire:confirm="Blokir {{ $user->name }}?"
                                class="mt-3 inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[2px] bg-sale px-5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity hover:opacity-85"
                            >
                                Blokir Akun
                            </button>
                        </form>
                    @endif
                </section>

                <section class="rounded-md border border-line bg-surface px-[18px] py-5">
                    <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Hapus Akun</h2>

                    @if ($this->deletionBlocker)
                        <p class="mt-2 text-[12.5px] text-muted">
                            Tidak bisa dihapus: {{ $this->deletionBlocker }} Riwayat transaksi harus tetap utuh, jadi blokir saja akun ini.
                        </p>
                    @else
                        <p class="mt-2 text-[12.5px] text-muted">Menghapus akun, alamat, keranjang, toko, dan semua barangnya secara permanen. Tidak bisa dibatalkan.</p>
                        <button
                            type="button"
                            wire:click="deleteAccount"
                            wire:confirm="Hapus {{ $user->name }} secara permanen? Tindakan ini tidak bisa dibatalkan."
                            class="mt-4 inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[2px] border border-sale px-5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-sale transition-colors hover:bg-sale hover:text-cream"
                        >
                            Hapus Permanen
                        </button>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</div>
