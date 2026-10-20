<?php

use App\Models\Dispute;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Tiket Bantuan')] class extends Component
{
    use WithPagination;

    /**
     * Label tiap status sengketa, sekaligus daftar status yang sah untuk filter.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'open' => 'Baru',
        'under_review' => 'Ditinjau',
        'resolved_buyer' => 'Selesai (pembeli)',
        'resolved_seller' => 'Selesai (penjual)',
        'cancelled' => 'Dibatalkan',
    ];

    #[Url]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Dispute>
     */
    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        return Dispute::with(['openedBy', 'storeOrder'])
            ->when(array_key_exists($this->status, self::STATUSES), fn ($query) => $query->where('status', $this->status))
            ->latest()
            ->paginate(15);
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Customer Service' => route('cs.dashboard'), 'Tiket' => null]"
        title="Tiket Bantuan"
        description="Sengketa pesanan yang diajukan pembeli."
        :links="['cs.dashboard' => 'Dashboard', 'cs.tickets.index' => 'Tiket', 'cs.chats.index' => 'Live Chat']"
    />

    <div class="mt-6">
        <label for="status" class="sr-only">Filter status</label>
        <select id="status" wire:model.live="status" class="rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px]">
            <option value="" @selected($status === '')>Semua status</option>
            @foreach ($this::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @if ($this->tickets->isEmpty())
        <p class="mt-4 rounded-md border border-line bg-surface px-[18px] py-6 text-center text-[13px] text-muted">Tidak ada tiket.</p>
    @else
        <div wire:loading.delay.class="pointer-events-none opacity-50" class="mt-4 overflow-x-auto rounded-md border border-line bg-surface transition-opacity duration-200">
            <table class="w-full text-left text-[12.5px]">
                <thead class="text-[11px] uppercase tracking-[0.1em] text-muted">
                    <tr>
                        <th class="px-[18px] py-3 font-semibold">Tiket</th>
                        <th class="px-[18px] py-3 font-semibold">Pesanan</th>
                        <th class="px-[18px] py-3 font-semibold">Pelapor</th>
                        <th class="px-[18px] py-3 font-semibold">Alasan</th>
                        <th class="px-[18px] py-3 font-semibold">Status</th>
                        <th class="px-[18px] py-3 font-semibold">Dibuat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->tickets as $ticket)
                        <tr wire:key="ticket-{{ $ticket->id }}" class="border-t border-line">
                            <td class="px-[18px] py-3 font-medium">#{{ $ticket->id }}</td>
                            <td class="px-[18px] py-3">{{ $ticket->storeOrder?->store_order_number }}</td>
                            <td class="px-[18px] py-3">{{ $ticket->openedBy?->name }}</td>
                            <td class="px-[18px] py-3">{{ str($ticket->reason)->replace('_', ' ')->ucfirst() }}</td>
                            <td class="px-[18px] py-3">
                                <span class="rounded-[3px] bg-cream px-2 py-0.5 text-[11px] font-semibold">{{ $this::STATUSES[$ticket->status] ?? $ticket->status }}</span>
                            </td>
                            <td class="px-[18px] py-3 text-muted">{{ $ticket->created_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pager :paginator="$this->tickets" />
    @endif
</div>
