<?php

use App\Models\Dispute;
use App\Models\StoreOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Tiket bantuan (sengketa paket) untuk CS: baca komplain pembeli, tandai
 * ditinjau, lalu putuskan dana ke pembeli (refund) atau ke penjual (cair).
 */
new class extends Component
{
    public Dispute $dispute;

    public string $resolutionNote = '';

    public function mount(Dispute $dispute): void
    {
        $this->dispute = $dispute;
    }

    public function markUnderReview(): void
    {
        $this->dispute->markUnderReview();
        $this->dispute->refresh();

        $this->dispatch('notify', message: 'Tiket ditandai sedang ditinjau.');
    }

    /**
     * @param  'buyer'|'seller'  $winner
     */
    public function resolve(string $winner): void
    {
        abort_unless(in_array($winner, ['buyer', 'seller'], true), 422);

        $this->validate(
            ['resolutionNote' => ['required', 'string', 'min:5', 'max:1000']],
            [
                'resolutionNote.required' => 'Tulis alasan keputusan (dibaca pembeli & penjual).',
                'resolutionNote.min' => 'Alasan keputusan minimal 5 karakter.',
                'resolutionNote.max' => 'Alasan keputusan maksimal 1.000 karakter.',
            ],
        );

        try {
            $this->dispute->resolve(Auth::user(), $winner, trim($this->resolutionNote));
        } catch (LogicException $exception) {
            $this->addError('resolutionNote', $exception->getMessage());

            return;
        }

        $this->dispute->refresh();
        $this->reset('resolutionNote');

        $this->dispatch('notify', message: $winner === 'buyer'
            ? 'Komplain disetujui. Dana dikembalikan ke saldo pembeli.'
            : 'Komplain ditolak. Dana dicairkan ke penjual.');
    }

    public function render(): View
    {
        return $this->view()->title('Tiket #'.$this->dispute->id);
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    @php
        $storeOrder = $dispute->storeOrder;
        $order = $storeOrder->order;
    @endphp

    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Customer Service' => route('cs.dashboard'), 'Tiket' => route('cs.tickets.index'), '#'.$dispute->id => null]"
        :title="'Tiket #'.$dispute->id.' · '.(Dispute::REASON_LABELS[$dispute->reason] ?? $dispute->reason)"
        :description="'Paket '.$storeOrder->store_order_number.' dari '.$storeOrder->store?->displayName().' · dibuka '.$dispute->created_at->format('d M Y, H:i')"
        :links="['cs.dashboard' => 'Dashboard', 'cs.tickets.index' => 'Tiket', 'cs.chats.index' => 'Live Chat']"
    />

    <div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex flex-col gap-4">
            <section class="rounded-md border border-line bg-surface px-[clamp(16px,3vw,24px)] py-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Komplain Pembeli</h2>
                    <span class="rounded-[3px] bg-cream px-2 py-0.5 text-[11.5px] font-semibold">{{ Dispute::STATUS_LABELS[$dispute->status] ?? $dispute->status }}</span>
                </div>
                <p class="mt-3 text-[13px] font-medium">{{ $dispute->openedBy?->name }} <span class="font-normal text-muted">&middot; {{ $dispute->openedBy?->email }}</span></p>
                <p class="mt-2 whitespace-pre-line text-[13.5px] leading-[1.7]">{{ $dispute->description }}</p>

                @if (! $dispute->isOpen())
                    <div class="mt-4 border-t border-line pt-3 text-[12.5px]">
                        <p class="font-semibold">Keputusan oleh {{ $dispute->resolvedBy?->name }} &middot; {{ $dispute->resolved_at?->format('d M Y, H:i') }}</p>
                        <p class="mt-1 text-muted">{{ $dispute->resolution_note }}</p>
                    </div>
                @endif
            </section>

            <section class="rounded-md border border-line bg-surface">
                <h2 class="border-b border-line px-[18px] py-3 text-[12px] font-semibold uppercase tracking-[0.1em]">Barang di Paket</h2>
                <ul class="divide-y divide-line">
                    @foreach ($storeOrder->items as $item)
                        <li wire:key="item-{{ $item->id }}" class="flex items-center justify-between gap-3 px-[18px] py-3 text-[12.5px]">
                            <span>
                                <span class="font-medium">{{ $item->product_name }}</span>
                                <span class="text-muted">{{ collect([$item->size_label, $item->color_name])->filter()->join(' · ') }} &times; {{ $item->quantity }}</span>
                            </span>
                            <span class="shrink-0 font-semibold">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="flex flex-col gap-1.5 border-t border-line px-[18px] py-3 text-[12.5px]">
                    <div class="flex justify-between gap-4"><dt class="text-muted">Status paket</dt><dd>{{ StoreOrder::BUYER_STATUS_LABELS[$storeOrder->status] ?? $storeOrder->status }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">Resi</dt><dd>{{ $storeOrder->shipment?->tracking_number ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4 font-semibold"><dt>Dana ditahan</dt><dd>Rp {{ number_format((float) $storeOrder->total, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">Pesanan</dt><dd>{{ $order->order_number }} &middot; {{ $order->recipient_name }}, {{ $order->shipping_city }}</dd></div>
                </dl>
            </section>
        </div>

        <aside class="flex flex-col gap-4">
            @if ($dispute->isOpen())
                <section class="rounded-md border border-line bg-surface px-[18px] py-5">
                    <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Keputusan</h2>

                    @if ($dispute->status === 'open')
                        <button type="button" wire:click="markUnderReview" class="mt-3 w-full cursor-pointer rounded-[2px] border border-line px-4 py-2.5 text-[11.5px] font-bold uppercase tracking-[0.1em] transition-colors hover:border-ink">Tandai Sedang Ditinjau</button>
                    @endif

                    <label for="resolutionNote" class="mt-4 block text-[12px] font-semibold">Alasan keputusan</label>
                    <textarea id="resolutionNote" rows="4" wire:model="resolutionNote" maxlength="1000" placeholder="Mis. foto dari pembeli menunjukkan jahitan lepas." class="mt-1.5 w-full rounded-[2px] border border-line bg-bg px-3 py-2 text-[13px] placeholder:text-muted">{{ $resolutionNote }}</textarea>
                    @error('resolutionNote') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror

                    <div class="mt-3 flex flex-col gap-2">
                        <button type="button" wire:click="resolve('buyer')" wire:confirm="Setujui komplain? Seluruh dana paket dikembalikan ke saldo pembeli." class="w-full cursor-pointer rounded-[2px] bg-sale px-4 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream hover:opacity-85">Setujui · Refund Pembeli</button>
                        <button type="button" wire:click="resolve('seller')" wire:confirm="Tolak komplain? Dana paket dicairkan ke penjual." class="w-full cursor-pointer rounded-[2px] bg-ink px-4 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream hover:opacity-85">Tolak · Cairkan ke Penjual</button>
                    </div>
                </section>
            @endif

            <a href="{{ route('cs.chats.index', ['user' => $dispute->opened_by]) }}" wire:navigate class="inline-flex items-center justify-center rounded-[2px] border border-line px-4 py-2.5 text-[11.5px] font-bold uppercase tracking-[0.1em] transition-colors hover:border-ink">Chat Pembeli</a>
        </aside>
    </div>
</div>
