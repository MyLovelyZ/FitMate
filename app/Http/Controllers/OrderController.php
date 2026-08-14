<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private CheckoutService $checkout) {}

    public function index(Request $request): View
    {
        return view('orders.index', [
            'orders' => $request->user()
                ->orders()
                ->with(['storeOrders.store:id,name', 'storeOrders.items'])
                ->latest('placed_at')
                ->paginate(10),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorize('view', $order);

        return view('orders.show', [
            'order' => $order->load([
                'storeOrders.store:id,name',
                'storeOrders.items.product:id,slug,name',
                'storeOrders.shipment',
                'coupon:id,code,name',
            ]),
            'payment' => $order->latestPayment(),
        ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('cancel', $order);

        $this->checkout->cancel($order);

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Pesanan dibatalkan dan stoknya sudah dikembalikan.');
    }
}
