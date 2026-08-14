<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ProductStatus;
use App\Enums\StoreOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        return view('seller.dashboard', [
            'store' => $store,
            'newOrderCount' => $store->storeOrders()->where('status', StoreOrderStatus::Pending)->count(),
            'toShipCount' => $store->storeOrders()->where('status', StoreOrderStatus::Processing)->count(),
            'activeProductCount' => $store->products()->where('status', ProductStatus::Active)->count(),
            'lowStockVariants' => $store->products()
                ->with(['variants' => fn ($query) => $query->where('stock', '<=', 3)->with('sizeChartEntry')])
                ->whereHas('variants', fn ($query) => $query->where('stock', '<=', 3))
                ->take(5)
                ->get(),
            'recentOrders' => $store->storeOrders()
                ->with(['order:id,order_number,placed_at', 'items'])
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
