<?php

use App\Models\Dispute;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard CS')] class extends Component
{
    /**
     * @return array<string, string>
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'Tiket Baru' => number_format(Dispute::where('status', 'open')->count(), 0, ',', '.'),
            'Sedang Ditinjau' => number_format(Dispute::where('status', 'under_review')->count(), 0, ',', '.'),
            'Menunggu Pembayaran' => number_format(Order::where('status', 'pending_payment')->count(), 0, ',', '.'),
        ];
    }

    /**
     * Tiket bantuan (sengketa) terbaru yang belum ditangani.
     *
     * @return Collection<int, Dispute>
     */
    #[Computed]
    public function latestTickets(): Collection
    {
        return Dispute::with(['openedBy', 'storeOrder'])
            ->where('status', 'open')
            ->latest()
            ->limit(5)
            ->get();
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Customer Service' => null]"
        title="Customer Service"
        description="Pantau tiket bantuan dan percakapan pelanggan."
        :links="['cs.dashboard' => 'Dashboard', 'cs.tickets.index' => 'Tiket', 'cs.chats.index' => 'Live Chat']"
    />

    {{-- Hitungan tiket dimuat lazy supaya kerangka halaman tampil lebih dulu. --}}
    @island(name: 'stats', lazy: true)
        @placeholder
            <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-3">
                @foreach (range(1, 3) as $index)
                    <x-ui.skeleton class="h-[86px]" />
                @endforeach
            </section>
        @endplaceholder

        <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-3">
            @foreach ($this->stats as $label => $value)
                <x-ui.stat :label="$label" :value="$value" />
            @endforeach
        </section>
    @endisland

    <section class="mt-10">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-[13px] font-semibold uppercase tracking-[0.1em]">Tiket Terbaru</h2>
            <a href="{{ route('cs.tickets.index') }}" wire:navigate class="text-[12.5px] font-medium hover:underline">Lihat semua</a>
        </div>

        @island(name: 'latest-tickets', lazy: true)
            @placeholder
                <div class="mt-3 flex flex-col gap-3">
                    @foreach (range(1, 3) as $index)
                        <x-ui.skeleton class="h-[92px]" />
                    @endforeach
                </div>
            @endplaceholder

            @forelse ($this->latestTickets as $ticket)
                <article wire:key="ticket-{{ $ticket->id }}" class="mt-3 rounded-md border border-line bg-surface px-[18px] py-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-[12px] text-muted">
                        <span>#{{ $ticket->id }} &middot; {{ $ticket->storeOrder?->store_order_number }}</span>
                        <span>{{ $ticket->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="mt-1.5 text-[13.5px] font-medium">{{ $ticket->openedBy?->name }} &mdash; {{ str($ticket->reason)->replace('_', ' ')->ucfirst() }}</p>
                    <p class="mt-1 line-clamp-2 text-[12.5px] text-muted">{{ $ticket->description }}</p>
                </article>
            @empty
                <p class="mt-3 rounded-md border border-line bg-surface px-[18px] py-6 text-center text-[13px] text-muted">Belum ada tiket baru.</p>
            @endforelse
        @endisland
    </section>
</div>
