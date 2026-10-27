<?php

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar akun untuk admin dan superadmin. Admin hanya melihat pelanggan dan
 * seller (`User::CUSTOMER_ROLES`); superadmin melihat semua role.
 */
new #[Title('Kelola Pengguna')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $role = '';

    /** '' = semua, 'aktif', atau 'diblokir'. */
    #[Url]
    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Role yang boleh dilihat akun yang sedang login: nilai => label.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function roles(): array
    {
        return Auth::user()->role === 'superadmin'
            ? User::ROLE_LABELS
            : Arr::only(User::ROLE_LABELS, User::CUSTOMER_ROLES);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function roleCounts(): array
    {
        return User::query()
            ->whereIn('role', array_keys($this->roles))
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->whereIn('role', array_keys($this->roles))
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->when(array_key_exists($this->role, $this->roles), fn ($query) => $query->where('role', $this->role))
            ->when($this->status === 'aktif', fn ($query) => $query->whereNull('banned_at'))
            ->when($this->status === 'diblokir', fn ($query) => $query->whereNotNull('banned_at'))
            ->latest()
            ->paginate(20);
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Admin' => route('admin.dashboard'), 'Pengguna' => null]"
        title="Kelola Pengguna"
        :description="auth()->user()->role === 'superadmin'
            ? 'Semua akun FitMate. Buka detail akun untuk memblokir, menghapus, mengubah role, atau melihat barang yang dijualnya.'
            : 'Pelanggan dan seller FitMate. Buka detail akun untuk memblokir, menghapus, atau melihat barang yang dijualnya.'"
        :links="auth()->user()->adminAreaLinks()"
    />

    {{-- Jumlah per role dimuat lazy; daftar pengguna di bawahnya tetap langsung tampil. --}}
    @island(name: 'role-counts', lazy: true)
        @placeholder
            <section class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($this->roles as $value => $label)
                    <x-ui.skeleton class="h-[86px]" />
                @endforeach
            </section>
        @endplaceholder

        <section class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($this->roles as $value => $label)
                <x-ui.stat :label="$label" :value="number_format($this->roleCounts[$value] ?? 0, 0, ',', '.')" />
            @endforeach
        </section>
    @endisland

    <div class="mt-6 flex flex-col gap-2 sm:flex-row">
        <label for="search" class="sr-only">Cari pengguna</label>
        <input
            id="search"
            type="search"
            wire:model.live.debounce.400ms="search"
            value="{{ $search }}"
            placeholder="Cari nama atau email…"
            class="w-full max-w-sm rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px]"
        >

        <label for="role" class="sr-only">Filter role</label>
        <select id="role" wire:model.live="role" class="rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px]">
            <option value="" @selected($role === '')>Semua role</option>
            @foreach ($this->roles as $value => $label)
                <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <label for="status" class="sr-only">Filter status</label>
        <select id="status" wire:model.live="status" class="rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px]">
            <option value="" @selected($status === '')>Semua status</option>
            <option value="aktif" @selected($status === 'aktif')>Aktif</option>
            <option value="diblokir" @selected($status === 'diblokir')>Diblokir</option>
        </select>
    </div>

    @if ($this->users->isEmpty())
        <p class="mt-4 rounded-md border border-line bg-surface px-[18px] py-6 text-center text-[13px] text-muted">Tidak ada pengguna yang cocok.</p>
    @else
        <div wire:loading.delay.class="pointer-events-none opacity-50" class="mt-4 overflow-x-auto rounded-md border border-line bg-surface transition-opacity duration-200">
            <table class="w-full text-left text-[12.5px]">
                <thead class="text-[11px] uppercase tracking-[0.1em] text-muted">
                    <tr>
                        <th class="px-[18px] py-3 font-semibold">Nama</th>
                        <th class="px-[18px] py-3 font-semibold">Email</th>
                        <th class="px-[18px] py-3 font-semibold">Role</th>
                        <th class="px-[18px] py-3 font-semibold">Status</th>
                        <th class="px-[18px] py-3 font-semibold">Bergabung</th>
                        <th class="px-[18px] py-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->users as $user)
                        <tr wire:key="user-{{ $user->id }}" class="border-t border-line">
                            <td class="px-[18px] py-3 font-medium">
                                <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="hover:underline">{{ $user->name }}</a>
                            </td>
                            <td class="px-[18px] py-3">{{ $user->email }}</td>
                            <td class="px-[18px] py-3">{{ $this->roles[$user->role] ?? $user->role }}</td>
                            <td class="px-[18px] py-3">
                                @if ($user->isBanned())
                                    <span class="rounded-[3px] bg-sale/10 px-2 py-0.5 text-[11px] font-semibold text-sale">Diblokir</span>
                                @else
                                    <span class="rounded-[3px] bg-cream px-2 py-0.5 text-[11px] font-semibold">{{ $user->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span>
                                @endif
                            </td>
                            <td class="px-[18px] py-3 text-muted">{{ $user->created_at?->format('d M Y') }}</td>
                            <td class="px-[18px] py-3 text-right">
                                <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="text-[12px] font-semibold underline-offset-4 hover:underline">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pager :paginator="$this->users" />
    @endif
</div>
