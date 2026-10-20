<?php

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kelola Pengguna')] class extends Component
{
    use WithPagination;

    /**
     * @var array<string, string>
     */
    public const ROLES = User::ROLE_LABELS;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $role = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function roleCounts(): array
    {
        return User::query()
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
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->when(array_key_exists($this->role, self::ROLES), fn ($query) => $query->where('role', $this->role))
            ->latest()
            ->paginate(20);
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Super Admin' => route('superadmin.dashboard'), 'Pengguna' => null]"
        title="Kelola Pengguna"
        description="Semua akun FitMate dari setiap role."
        :links="['superadmin.users.index' => 'Pengguna', 'superadmin.settings' => 'Pengaturan']"
    />

    {{-- Jumlah per role dimuat lazy; daftar pengguna di bawahnya tetap langsung tampil. --}}
    @island(name: 'role-counts', lazy: true)
        @placeholder
            <section class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach (range(1, 5) as $index)
                    <x-ui.skeleton class="h-[86px]" />
                @endforeach
            </section>
        @endplaceholder

        <section class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($this::ROLES as $value => $label)
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
            @foreach ($this::ROLES as $value => $label)
                <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
            @endforeach
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
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->users as $user)
                        <tr wire:key="user-{{ $user->id }}" class="border-t border-line">
                            <td class="px-[18px] py-3 font-medium">{{ $user->name }}</td>
                            <td class="px-[18px] py-3">{{ $user->email }}</td>
                            <td class="px-[18px] py-3">{{ $this::ROLES[$user->role] ?? $user->role }}</td>
                            <td class="px-[18px] py-3">
                                <span class="rounded-[3px] bg-cream px-2 py-0.5 text-[11px] font-semibold">{{ $user->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>
                            <td class="px-[18px] py-3 text-muted">{{ $user->created_at?->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pager :paginator="$this->users" />
    @endif
</div>
