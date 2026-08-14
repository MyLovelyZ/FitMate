<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StoreOrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StoreOrder;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Memecah satu keranjang menjadi satu `orders` dan beberapa `store_orders`.
 *
 * Seluruh proses berjalan di dalam satu transaksi database dan mengunci baris
 * varian yang dibeli, supaya dua orang yang checkout barang terakhir bersamaan
 * tidak sama-sama berhasil.
 *
 * Semua data produk disalin ke `order_items`, bukan direferensikan: kalau besok
 * seller mengubah harga atau menghapus produknya, nota lama harus tetap
 * menunjukkan angka yang berlaku saat pembelian.
 */
class CheckoutService
{
    public function __construct(
        private ShippingService $shipping,
        private CouponService $coupons,
        private SizeRecommendationService $recommendations,
    ) {}

    /**
     * @param  array{courier?: string, payment_method?: string, coupon_code?: string|null, note?: string|null}  $options
     *
     * @throws ValidationException
     */
    public function place(User $user, Cart $cart, UserAddress $address, array $options = []): Order
    {
        $cart->loadItemsForDisplay();

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Keranjangmu masih kosong.',
            ]);
        }

        $this->guardAgainstInactiveProducts($cart->items);

        return DB::transaction(function () use ($user, $cart, $address, $options): Order {
            $grouped = $cart->itemsGroupedByStore();

            $this->lockAndVerifyStock($cart->items);

            $subtotal = $cart->subtotal();
            $shippingTotal = $grouped->sum(fn (Collection $items): float => $this->shipping->costFor($items));

            $coupon = null;
            $discountTotal = 0.0;

            if (! empty($options['coupon_code'])) {
                $coupon = $this->coupons->resolve($options['coupon_code'], $user, $subtotal);
                $discountTotal = $coupon->calculateDiscount($subtotal);
            }

            $order = Order::create([
                'user_id' => $user->id,
                'coupon_id' => $coupon?->id,
                'recipient_name' => $address->recipient_name,
                'recipient_phone' => $address->recipient_phone,
                'shipping_street' => $address->street,
                'shipping_city' => $address->city,
                'shipping_state' => $address->state,
                'shipping_postal_code' => $address->postal_code,
                'shipping_country' => $address->country ?? 'Indonesia',
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'discount_total' => $discountTotal,
                'grand_total' => max(0, $subtotal + $shippingTotal - $discountTotal),
                'status' => OrderStatus::PendingPayment,
                'note' => $options['note'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($grouped as $storeId => $items) {
                $this->createStoreOrder($order, (int) $storeId, $items, $subtotal, $discountTotal, $options);
            }

            $this->createPayment($order, $options['payment_method'] ?? PaymentMethod::BankTransfer->value);

            if ($coupon instanceof Coupon) {
                $this->coupons->recordUsage($coupon, $order, $discountTotal);
            }

            $cart->items()->delete();

            return $order;
        });
    }

    /**
     * Kembalikan stok dan tutup seluruh pecahan order saat pesanan dibatalkan.
     */
    public function cancel(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->load('storeOrders.items');

            foreach ($order->storeOrders as $storeOrder) {
                if (in_array($storeOrder->status, [StoreOrderStatus::Cancelled, StoreOrderStatus::Refunded], true)) {
                    continue;
                }

                foreach ($storeOrder->items as $item) {
                    if ($item->product_variant_id !== null) {
                        ProductVariant::whereKey($item->product_variant_id)->increment('stock', $item->quantity);
                    }
                }

                $storeOrder->update(['status' => StoreOrderStatus::Cancelled]);
            }

            $order->update(['status' => OrderStatus::Cancelled]);

            $order->payments()
                ->where('status', PaymentStatus::Pending)
                ->update(['status' => PaymentStatus::Failed]);
        });
    }

    /**
     * @param  Collection<int, CartItem>  $items
     */
    private function guardAgainstInactiveProducts(Collection $items): void
    {
        foreach ($items as $item) {
            $product = $item->variant->product;

            if (! $item->variant->is_active || $product === null || ! $product->isActive()) {
                throw ValidationException::withMessages([
                    'cart' => "Produk \"{$item->variant->product?->name}\" sudah tidak dijual. Hapus dulu dari keranjang.",
                ]);
            }
        }
    }

    /**
     * Kunci baris varian selama transaksi, lalu pastikan stoknya masih cukup.
     *
     * @param  Collection<int, CartItem>  $items
     */
    private function lockAndVerifyStock(Collection $items): void
    {
        $variantIds = $items->pluck('product_variant_id')->unique()->sort()->values();

        /** @var array<int, ProductVariant> $locked */
        $locked = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id')
            ->all();

        foreach ($items as $item) {
            $variant = $locked[$item->product_variant_id] ?? null;

            if ($variant === null || $variant->stock < $item->quantity) {
                throw ValidationException::withMessages([
                    'cart' => sprintf(
                        'Stok "%s" tinggal %d, sedangkan kamu memesan %d.',
                        $item->variant->product?->name ?? 'produk',
                        $variant?->stock ?? 0,
                        $item->quantity,
                    ),
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, CartItem>  $items
     * @param  array<string, mixed>  $options
     */
    private function createStoreOrder(
        Order $order,
        int $storeId,
        Collection $items,
        float $orderSubtotal,
        float $orderDiscount,
        array $options,
    ): StoreOrder {
        $storeSubtotal = (float) $items->sum(fn (CartItem $item): float => $item->subtotal());
        $shippingCost = $this->shipping->costFor($items);

        // Potongan kupon level order dibagi ke tiap toko sebanding kontribusinya,
        // supaya penyelesaian dana ke seller nanti tetap adil.
        $discount = $orderSubtotal > 0
            ? round($orderDiscount * ($storeSubtotal / $orderSubtotal), 2)
            : 0.0;

        $storeOrder = $order->storeOrders()->create([
            'store_id' => $storeId,
            'subtotal' => $storeSubtotal,
            'shipping_cost' => $shippingCost,
            'discount' => $discount,
            'total' => max(0, $storeSubtotal + $shippingCost - $discount),
            'status' => StoreOrderStatus::Pending,
        ]);

        foreach ($items as $item) {
            $storeOrder->items()->create($this->snapshotItem($item, $order));

            ProductVariant::whereKey($item->product_variant_id)->decrement('stock', $item->quantity);
        }

        $storeOrder->shipment()->create([
            'courier' => $options['courier'] ?? array_key_first($this->shipping->couriers()),
            'service' => 'reg',
            'cost' => $shippingCost,
            'weight_gram' => $this->shipping->totalWeight($items),
        ]);

        return $storeOrder;
    }

    /**
     * Salin seluruh data yang perlu bertahan di nota, termasuk ukuran yang
     * disarankan FitMate saat itu — itulah yang nanti dibandingkan dengan ukuran
     * yang benar-benar dibeli untuk mengukur kepatuhan rekomendasi (BE-083).
     *
     * @return array<string, mixed>
     */
    private function snapshotItem(CartItem $item, Order $order): array
    {
        $variant = $item->variant;
        $product = $variant->product;

        return [
            'product_id' => $product?->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product?->name ?? 'Produk',
            'size_label' => $variant->sizeLabel(),
            'color_name' => $variant->color_name,
            'unit_price' => $variant->price,
            'quantity' => $item->quantity,
            'subtotal' => $item->subtotal(),
            'recommended_size_label' => $this->recommendedLabelFor($order, $item),
        ];
    }

    private function recommendedLabelFor(Order $order, CartItem $item): ?string
    {
        $product = $item->variant->product;
        $profile = $order->user->activeBodyProfile();

        if ($product === null || $profile === null) {
            return null;
        }

        return $this->recommendations->forProduct($profile, $product)?->label();
    }

    private function createPayment(Order $order, string $method): void
    {
        $order->payments()->create([
            'method' => PaymentMethod::tryFrom($method) ?? PaymentMethod::BankTransfer,
            'amount' => $order->grand_total,
            'status' => PaymentStatus::Pending,
            'expires_at' => now()->addHours((int) config('fitmate.payment.expires_after_hours')),
        ]);
    }
}
