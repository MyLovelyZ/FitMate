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
        'processing' => 'Diproses',
        'shipped' => 'Dikirim',
        'delivered' => 'Diterima',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'refunded' => 'Dikembalikan',
        'disputed' => 'Sengketa',
    ];

    #[Url]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Sub-pesanan milik toko user yang login; kosong kalau user belum punya toko.
     *
     * @return LengthAwarePaginator<int, StoreOrder>
     */
    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return StoreOrder::with('order.user')
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
        :breadcrumbs="['Beranda' => route('home'), 'Seller' => route('seller.dashboard'), 'Pesanan' => null]"
        title="Pesanan Toko"
        :links="['seller.dashboard' => 'Dashboard', 'seller.products.index' => 'Produk', 'seller.orders.index' => 'Pesanan']"
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
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->orders as $storeOrder)
                        <tr wire:key="store-order-{{ $storeOrder->id }}" class="border-t border-line">
                            <td class="px-[18px] py-3 font-medium">{{ $storeOrder->store_order_number }}</td>
                            <td class="px-[18px] py-3">{{ $storeOrder->order?->user?->name }}</td>
                            <td class="px-[18px] py-3">{{ $storeOrder->items_count }}</td>
                            <td class="px-[18px] py-3">Rp {{ number_format((float) $storeOrder->total, 0, ',', '.') }}</td>
                            <td class="px-[18px] py-3">
                                <span class="rounded-[3px] bg-cream px-2 py-0.5 text-[11px] font-semibold">{{ $this::STATUSES[$storeOrder->status] ?? $storeOrder->status }}</span>
                            </td>
                            <td class="px-[18px] py-3 text-muted">{{ $storeOrder->created_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pager :paginator="$this->orders" />
    @endif
</div>
