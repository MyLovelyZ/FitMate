<?php

use App\Models\StoreOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pesanan Toko')] class extends Component
{
    use WithPagination;

    /**
     * @var array<string, string>
     */
    public const STATUSES = [
        'pending' => 'Baru',
        'processing' => 'Dikemas',
        'shipped' => 'Dikirim',
        'delivered' => 'Diterima',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'refunded' => 'Dikembalikan',
        'disputed' => 'Sengketa',
    ];

    #[Url]
    public string $status = '';

    /** Paket yang sedang dibuka form "Ubah Status"-nya. */
    public ?int $editingId = null;

    public string $newStatus = '';

    public string $trackingNumber = '';

    public string $cancelReason = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
        $this->closeStatusForm();
    }

    public function editStatus(int $storeOrderId): void
    {
        $storeOrder = $this->ownedStoreOrder($storeOrderId);

        $this->resetValidation();
        $this->editingId = $storeOrder->id;
        $this->newStatus = (string) array_key_first($storeOrder->sellerStatusOptions());
        $this->trackingNumber = (string) $storeOrder->shipment?->tracking_number;
        $this->cancelReason = '';
    }

    public function closeStatusForm(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'newStatus', 'trackingNumber', 'cancelReason');
    }

    /**
     * Seller mengubah status pesanan langsung dari daftar.
     */
    public function saveStatus(): void
    {
        $storeOrder = $this->ownedStoreOrder((int) $this->editingId);
        $options = $storeOrder->sellerStatusOptions();

        $this->validate([
            'newStatus' => ['required', 'in:'.implode(',', array_keys($options))],
            'trackingNumber' => ['exclude_unless:newStatus,shipped', 'required', 'string', 'max:64'],
            'cancelReason' => ['exclude_unless:newStatus,cancelled', 'required', 'string', 'max:255'],
        ], [
            'newStatus.required' => 'Pilih status baru.',
            'newStatus.in' => 'Status ini tidak bisa dipilih untuk pesanan tersebut.',
            'trackingNumber.required' => 'Nomor resi wajib diisi untuk status Dikirim.',
            'trackingNumber.max' => 'Nomor resi maksimal 64 karakter.',
            'cancelReason.required' => 'Tulis alasan pembatalan (dibaca pembeli).',
            'cancelReason.max' => 'Alasan maksimal 255 karakter.',
        ]);

        try {
            $storeOrder->changeStatusBySeller($this->newStatus, Auth::user(), $this->trackingNumber, $this->cancelReason);
        } catch (LogicException $exception) {
            $this->addError('newStatus', $exception->getMessage());

            return;
        }

        $message = "Status {$storeOrder->store_order_number} diubah ke ".$options[$this->newStatus].'. Pembeli sudah diberi notifikasi.';

        $this->closeStatusForm();
        unset($this->orders);

        $this->dispatch('notify', message: $message);
    }

    protected function ownedStoreOrder(int $storeOrderId): StoreOrder
    {
        $storeOrder = StoreOrder::with(['order', 'shipment'])
            ->where('store_id', Auth::user()->store?->id ?? 0)
            ->find($storeOrderId);

        abort_if($storeOrder === null, 404);

        return $storeOrder;
    }

    /**
     * Sub-pesanan milik toko user yang login; kosong kalau user belum punya toko.
     *
     * @return LengthAwarePaginator<int, StoreOrder>
     */
    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return StoreOrder::with(['order.user', 'shipment'])
            ->withCount('items')
            ->where('store_id', Auth::user()->store?->id ?? 0)
            ->when(array_key_exists($this->status, self::STATUSES), fn ($query) => $query->where('status', $this->status))
            ->latest()
            ->paginate(15);
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Seller Center' => route('seller.dashboard'), 'Pesanan' => null]"
        title="Pesanan Toko"
        :links="auth()->user()->sellerAreaLinks()"
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

    @if ($this->orders->isEmpty())
        <p class="mt-4 rounded-md border border-line bg-surface px-[18px] py-6 text-center text-[13px] text-muted">Belum ada pesanan.</p>
    @else
        <div wire:loading.delay.class="pointer-events-none opacity-50" class="mt-4 overflow-x-auto rounded-md border border-line bg-surface transition-opacity duration-200">
            <table class="w-full text-left text-[12.5px]">
                <thead class="text-[11px] uppercase tracking-[0.1em] text-muted">
                    <tr>
                        <th class="px-[18px] py-3 font-semibold">No. Pesanan</th>
                        <th class="px-[18px] py-3 font-semibold">Pembeli</th>
                        <th class="px-[18px] py-3 font-semibold">Item</th>
                        <th class="px-[18px] py-3 font-semibold">Total</th>
                        <th class="px-[18px] py-3 font-semibold">Status</th>
                        <th class="px-[18px] py-3 font-semibold">Tanggal</th>
                        <th class="px-[18px] py-3 font-semibold"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->orders as $storeOrder)
                        <tr wire:key="store-order-{{ $storeOrder->id }}" class="border-t border-line">
                            <td class="px-[18px] py-3 font-medium">
                                <a href="{{ route('seller.orders.show', $storeOrder) }}" wire:navigate class="underline-offset-4 hover:underline">{{ $storeOrder->store_order_number }}</a>
                            </td>
                            <td class="px-[18px] py-3">{{ $storeOrder->order?->user?->name }}</td>
                            <td class="px-[18px] py-3">{{ $storeOrder->items_count }}</td>
                            <td class="px-[18px] py-3">Rp {{ number_format((float) $storeOrder->total, 0, ',', '.') }}</td>
                            <td class="px-[18px] py-3">
                                <span class="rounded-[3px] bg-cream px-2 py-0.5 text-[11px] font-semibold">
                                    {{ $storeOrder->status === 'pending' && ! $storeOrder->order?->isPaid() ? 'Menunggu Bayar' : ($this::STATUSES[$storeOrder->status] ?? $storeOrder->status) }}
                                </span>
                            </td>
                            <td class="px-[18px] py-3 text-muted">{{ $storeOrder->created_at->format('d M Y') }}</td>
                            <td class="px-[18px] py-3 text-right">
                                @if ($storeOrder->sellerStatusOptions() !== [] && $editingId !== $storeOrder->id)
                                    <button
                                        type="button"
                                        wire:click="editStatus({{ $storeOrder->id }})"
                                        class="cursor-pointer whitespace-nowrap rounded-[2px] border border-line px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.08em] transition-colors hover:border-ink"
                                    >
                                        Ubah Status
                                    </button>
                                @endif
                            </td>
                        </tr>

                        @if ($editingId === $storeOrder->id)
                            @php
                                $options = $storeOrder->sellerStatusOptions();
                            @endphp

                            <tr wire:key="store-order-form-{{ $storeOrder->id }}" class="bg-bg">
                                <td colspan="7" class="px-[18px] py-4">
                                    <form wire:submit="saveStatus" class="flex flex-col gap-3">
                                        <p class="text-[12px] font-semibold uppercase tracking-[0.1em]">Ubah Status {{ $storeOrder->store_order_number }}</p>

                                        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Status baru">
                                            @foreach ($options as $value => $label)
                                                <label @class([
                                                    'cursor-pointer rounded-[2px] border px-4 py-2 text-[12px] font-semibold transition-colors',
                                                    'border-ink bg-ink text-cream' => $newStatus === $value,
                                                    'border-line bg-surface hover:border-ink' => $newStatus !== $value,
                                                ])>
                                                    <input type="radio" wire:model.live="newStatus" value="{{ $value }}" class="sr-only" @checked($newStatus === $value)>
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('newStatus') <p class="text-[12px] text-sale">{{ $message }}</p> @enderror

                                        @if ($newStatus === 'shipped')
                                            <div class="flex max-w-md flex-col gap-1.5">
                                                <label for="trackingNumber-{{ $storeOrder->id }}" class="text-[12px] font-semibold">Nomor resi {{ $storeOrder->shipment?->courierLabel() }}</label>
                                                <input
                                                    id="trackingNumber-{{ $storeOrder->id }}"
                                                    type="text"
                                                    wire:model="trackingNumber"
                                                    value="{{ $trackingNumber }}"
                                                    maxlength="64"
                                                    class="h-10 rounded-[2px] border border-line bg-surface px-3 text-[13px] outline-none transition-colors focus:border-ink"
                                                >
                                                @error('trackingNumber') <p class="text-[12px] text-sale">{{ $message }}</p> @enderror
                                            </div>
                                        @elseif ($newStatus === 'cancelled')
                                            <div class="flex max-w-md flex-col gap-1.5">
                                                <label for="cancelReason-{{ $storeOrder->id }}" class="text-[12px] font-semibold">Alasan pembatalan (dibaca pembeli)</label>
                                                <textarea
                                                    id="cancelReason-{{ $storeOrder->id }}"
                                                    rows="2"
                                                    wire:model="cancelReason"
                                                    maxlength="255"
                                                    placeholder="Mis. stok ternyata rusak"
                                                    class="rounded-[2px] border border-line bg-surface px-3 py-2 text-[13px] outline-none transition-colors placeholder:text-muted focus:border-ink"
                                                >{{ $cancelReason }}</textarea>
                                                @error('cancelReason') <p class="text-[12px] text-sale">{{ $message }}</p> @enderror
                                                <p class="text-[12px] text-muted">Stok dikembalikan dan seluruh dana paket ini dikembalikan ke saldo pembeli.</p>
                                            </div>
                                        @elseif ($newStatus === 'delivered')
                                            <p class="text-[12px] text-muted">Tandai paket sudah sampai di alamat pembeli. Dana cair otomatis {{ App\Models\EscrowHold::AUTO_RELEASE_DAYS }} hari kemudian kalau pembeli tidak komplain.</p>
                                        @endif

                                        <div class="flex items-center gap-3">
                                            <button
                                                type="submit"
                                                class="cursor-pointer rounded-[2px] bg-ink px-5 py-2.5 text-[11px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity hover:opacity-85 disabled:cursor-wait disabled:opacity-60"
                                            >
                                                <span wire:loading.remove wire:target="saveStatus">Simpan Status</span>
                                                <span wire:loading wire:target="saveStatus">Menyimpan…</span>
                                            </button>
                                            <button type="button" wire:click="closeStatusForm" class="cursor-pointer text-[12px] font-semibold text-muted hover:text-ink">Batal</button>
                                            <a href="{{ route('seller.orders.show', $storeOrder) }}" wire:navigate class="ml-auto text-[12px] font-semibold text-muted underline-offset-4 hover:text-ink hover:underline">Detail &amp; lacak paket</a>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pager :paginator="$this->orders" />
    @endif
</div>
