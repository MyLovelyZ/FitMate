<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\UserAddress;
use App\Services\CheckoutService;
use App\Services\CouponService;
use App\Services\ShippingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkout,
        private ShippingService $shipping,
        private CouponService $coupons,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $cart = $request->user()->currentCart()->loadItemsForDisplay();

        if ($cart->items->isEmpty()) {
            return redirect()
                ->route('cart.index')
                ->with('warning', 'Keranjangmu masih kosong, belum ada yang bisa di-checkout.');
        }

        $itemsByStore = $cart->itemsGroupedByStore();
        $subtotal = $cart->subtotal();
        $shippingTotal = $itemsByStore->sum(fn (Collection $items): float => $this->shipping->costFor($items));

        $coupon = $this->coupons->tryResolve($request->query('coupon_code'), $request->user(), $subtotal);
        $discount = $coupon?->calculateDiscount($subtotal) ?? 0.0;

        return view('checkout.index', [
            'cart' => $cart,
            'itemsByStore' => $itemsByStore,
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->get(),
            'couriers' => $this->shipping->couriers(),
            'paymentMethods' => $this->paymentMethods(),
            'shippingPerStore' => $itemsByStore->map(fn (Collection $items): float => $this->shipping->costFor($items)),
            'subtotal' => $subtotal,
            'shippingTotal' => $shippingTotal,
            'discount' => $discount,
            'grandTotal' => max(0, $subtotal + $shippingTotal - $discount),
            'appliedCoupon' => $coupon,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_address_id' => ['required', 'integer', 'exists:user_addresses,id'],
            'courier' => ['required', 'string', 'in:'.implode(',', array_keys($this->shipping->couriers()))],
            'payment_method' => ['required', 'string', 'in:'.implode(',', array_keys($this->paymentMethods()))],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $address = UserAddress::where('user_id', $request->user()->id)
            ->findOrFail($validated['user_address_id']);

        $order = $this->checkout->place(
            $request->user(),
            $request->user()->currentCart(),
            $address,
            $validated,
        );

        return redirect()->route('checkout.success', $order);
    }

    public function success(Order $order): View
    {
        $this->authorize('view', $order);

        return view('checkout.success', [
            'order' => $order->load('storeOrders.store'),
        ]);
    }

    /**
     * KEP-3 belum diputuskan, jadi baru metode yang tidak butuh gateway.
     *
     * @return array<string, string>
     */
    private function paymentMethods(): array
    {
        return collect(config('fitmate.payment.enabled_methods'))
            ->mapWithKeys(fn (string $method): array => [
                $method => PaymentMethod::from($method)->label(),
            ])
            ->all();
    }
}
