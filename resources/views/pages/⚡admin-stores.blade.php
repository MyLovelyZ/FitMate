<?php

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pengajuan & Toko')] class extends Component
{
    use WithPagination;

    /** Kunci `Store::STATUS_LABELS`. Default ke antrean yang perlu ditinjau. */
    #[Url]
    public string $status = 'pending';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        if (! array_key_exists($this->status, Store::STATUS_LABELS)) {
            $this->status = 'pending';
        }
    }

    public function setStatus(string $status): void
    {
        if (array_key_exists($status, Store::STATUS_LABELS)) {
            $this->status = $status;
            $this->resetPage();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function statusLabels(): array
    {
        return Store::STATUS_LABELS;
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return Store::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * Antrean pending diurutkan dari yang paling lama menunggu.
     *
     * @return LengthAwarePaginator<int, Store>
     */
    #[Computed]
    public function stores(): LengthAwarePaginator
    {
        return Store::query()
            ->with('owner')
            ->where('status', $this->status)
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhereHas('owner', fn ($owner) => $owner
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%'));
            }))
            ->when(
                $this->status === 'pending',
                fn ($query) => $query->orderBy('submitted_at'),
                fn ($query) => $query->latest('reviewed_at')->latest(),
            )
            ->paginate(15);
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Admin' => route('admin.dashboard'), 'Toko' => null]"
        title="Pengajuan & Toko"
        description="Tinjau pengajuan toko baru dan kelola toko yang sudah berjalan."
        :links="['admin.dashboard' => 'Dashboard', 'admin.stores.index' => 'Toko']"
    />

    <div class="mt-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Filter status toko">
            @foreach ($this->statusLabels as $key => $label)
                <button
                    type="button"
                    wire:key="status-{{ $key }}"
                    wire:click="setStatus('{{ $key }}')"
                    aria-pressed="{{ $status === $key ? 'true' : 'false' }}"
                    @class([
                        'inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-[2px] border px-4 py-2 text-[12.5px] font-semibold transition-colors duration-150 data-loading:opacity-60',
                        'border-ink bg-ink text-cream' => $status === $key,
                        'border-line bg-surface hover:border-ink' => $status !== $key,
                    ])
                >
                    {{ $label }}
                    <span @class([
                        'min-w-5 rounded-full px-1.5 text-center text-[11px]',
                        'bg-cream/20' => $status === $key,
                        'bg-line/70 text-muted' => $status !== $key,
                    ])>{{ $this->counts[$key] ?? 0 }}</span>
                </button>
            @endforeach
        </nav>

        <label for="search" class="sr-only">Cari toko</label>
        <input
            id="search"
            type="search"
            wire:model.live.debounce.400ms="search"
            value="{{ $search }}"
            placeholder="Cari nama toko atau pemilik…"
            class="w-full rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px] lg:max-w-xs"
        >
    </div>

    @if ($this->stores->isEmpty())
        <p class="mt-4 rounded-md border border-line bg-surface px-[18px] py-10 text-center text-[13px] text-muted">
            {{ $status === 'pending' ? 'Tidak ada pengajuan yang menunggu review.' : 'Tidak ada toko dengan status ini.' }}
        </p>
    @else
        <div wire:loading.delay.class="pointer-events-none opacity-50" class="mt-4 overflow-x-auto rounded-md border border-line bg-surface transition-opacity duration-200">
            <table class="w-full text-left text-[12.5px]">
                <thead class="text-[11px] uppercase tracking-[0.1em] text-muted">
                    <tr>
                        <th class="px-[18px] py-3 font-semibold">Toko</th>
                        <th class="px-[18px] py-3 font-semibold">Pemilik</th>
                        <th class="px-[18px] py-3 font-semibold">Kota</th>
                        <th class="px-[18px] py-3 font-semibold">{{ $status === 'pending' ? 'Diajukan' : 'Ditinjau' }}</th>
                        <th class="px-[18px] py-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->stores as $store)
                        <tr wire:key="store-{{ $store->id }}" class="border-t border-line">
                            <td class="px-[18px] py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-cream text-[11px] font-semibold">
                                        @if ($store->logo)
                                            <img src="{{ asset('storage/'.$store->logo) }}" alt="" loading="lazy" class="size-full object-cover">
                                        @else
                                            {{ str($store->name)->substr(0, 1)->upper() }}
                                        @endif
                                    </span>
                                    <span class="font-medium">{{ $store->name }}</span>
                                </div>
                            </td>
                            <td class="px-[18px] py-3">
                                <p>{{ $store->owner?->name }}</p>
                                <p class="text-[11.5px] text-muted">{{ $store->owner?->email }}</p>
                            </td>
                            <td class="px-[18px] py-3 text-muted">{{ $store->city ?? '—' }}</td>
                            <td class="px-[18px] py-3 text-muted">
                                {{ ($status === 'pending' ? $store->submitted_at : $store->reviewed_at)?->format('d M Y, H:i') ?? '—' }}
                            </td>
                            <td class="px-[18px] py-3 text-right">
                                <a
                                    href="{{ route('admin.stores.show', $store) }}"
                                    wire:navigate.hover
                                    class="inline-flex items-center rounded-[2px] border border-line px-3 py-1.5 text-[12px] font-semibold transition-colors hover:border-ink"
                                >
                                    {{ $status === 'pending' ? 'Tinjau' : 'Detail' }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pager :paginator="$this->stores" />
    @endif
</div>
