<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ShipmentStatus;
use App\Enums\StoreOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BE-077: seller hanya boleh melihat dan memproses pesanan tokonya sendiri.
 * Daftarnya selalu dibatasi lewat relasi toko, bukan lewat filter di query
 * string — kalau tidak, id tebakan bisa membocorkan pesanan toko lain.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        return view('seller.orders.index', [
            'storeOrders' => $store->storeOrders()
                ->with(['order:id,order_number,recipient_name,placed_at', 'items', 'shipment'])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'statusOptions' => StoreOrderStatus::options(),
        ]);
    }

    public function show(StoreOrder $storeOrder): View
    {
        $this->authorize('view', $storeOrder);

        return view('seller.orders.show', [
            'storeOrder' => $storeOrder->load(['order', 'items.product:id,slug,name', 'shipment']),
            'nextStatuses' => $storeOrder->allowedNextStatuses(),
            'couriers' => config('fitmate.shipping.couriers'),
        ]);
    }

    public function updateStatus(Request $request, StoreOrder $storeOrder): RedirectResponse
    {
        $this->authorize('update', $storeOrder);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_column(StoreOrderStatus::cases(), 'value'))],
        ]);

        $status = StoreOrderStatus::from($validated['status']);

        if (! $storeOrder->canTransitionTo($status)) {
            return back()->with('error', "Status tidak bisa langsung berpindah dari {$storeOrder->status->label()} ke {$status->label()}.");
        }

        $storeOrder->transitionTo($status);

        return back()->with('success', 'Status pesanan diperbarui.');
    }

    /**
     * BE-076: seller mengisi resi, lalu pesanan berpindah ke status "dikirim".
     */
    public function ship(Request $request, StoreOrder $storeOrder): RedirectResponse
    {
        $this->authorize('update', $storeOrder);

        $validated = $request->validate([
            'courier' => ['required', 'string', 'in:'.implode(',', array_keys(config('fitmate.shipping.couriers')))],
            'service' => ['nullable', 'string', 'max:50'],
            'tracking_number' => ['required', 'string', 'max:100'],
        ]);

        if (! $storeOrder->canTransitionTo(StoreOrderStatus::Shipped)) {
            return back()->with('error', 'Pesanan ini belum siap dikirim.');
        }

        $storeOrder->shipment()->updateOrCreate([], [
            ...$validated,
            'status' => ShipmentStatus::InTransit,
            'shipped_at' => now(),
            'cost' => $storeOrder->shipping_cost,
            'weight_gram' => $storeOrder->shipment?->weight_gram ?? 0,
        ]);

        $storeOrder->transitionTo(StoreOrderStatus::Shipped);

        return back()->with('success', 'Resi tersimpan, pesanan ditandai dikirim.');
    }
}
