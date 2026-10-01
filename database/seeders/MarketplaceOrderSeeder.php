<?php

namespace Database\Seeders;

use App\Models\Dispute;
use App\Models\EscrowHold;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class MarketplaceOrderSeeder extends Seeder
{
    /**
     * Delapan pesanan demo, satu untuk tiap tahap perjalanan uang — dari QR yang
     * belum dibayar sampai saldo yang sudah ditarik seller ke rekening.
     *
     * Butuh ProductSeeder, StoreSeeder, dan WalletSeeder jalan duluan.
     */
    private const PLATFORM_FEE_RATE = 0.05;

    private const AUTO_RELEASE_DAYS = 7;

    private int $sequence = 0;

    private int $variantOffset = 0;

    public function run(): void
    {
        // Order demo dibuat pakai factory, jadi ngk idempotent. Kalau sudah ada,
        // lewati — pakai `migrate:fresh --seed` kalau mau data baru.
        if (Order::query()->exists()) {
            return;
        }

        $buyers = $this->buyers();
        $stores = Store::where('status', 'active')->orderBy('id')->get();

        if ($buyers->isEmpty() || $stores->isEmpty()) {
            throw new \RuntimeException('UserSeeder, StoreSeeder, dan WalletSeeder harus jalan sebelum MarketplaceOrderSeeder.');
        }

        $this->awaitingPayment($buyers[0], $stores[0]);
        $this->paidAndProcessing($buyers[0], $stores[1 % $stores->count()]);
        $this->onTheWay($buyers[1 % $buyers->count()], $stores[2 % $stores->count()]);
        $this->awaitingBuyerConfirmation($buyers[1 % $buyers->count()], $stores[0]);
        $this->readyForAutoRelease($buyers[2 % $buyers->count()], $stores[1 % $stores->count()]);
        $this->completedAndPaidOut($buyers[2 % $buyers->count()], $stores[2 % $stores->count()]);
        $this->underDispute($buyers[3 % $buyers->count()], $stores[0]);
        $this->refundedToWallet($buyers[3 % $buyers->count()], $stores[1 % $stores->count()]);
    }

    // -----------------------------------------------------------------
    // Delapan tahap perjalanan uang
    // -----------------------------------------------------------------

    /**
     * 1. Pembeli sudah dapat kode QR tapi belum bayar. Belum ada uang, jadi belum
     *    ada escrow sama sekali.
     */
    private function awaitingPayment(User $buyer, Store $store): void
    {
        [$order, $storeOrder] = $this->placeOrder($buyer, $store);

        $this->createPayment($order, 'pending');

        $storeOrder->update(['status' => 'pending']);
    }

    /**
     * 2. Pembayaran masuk. Uangnya ada di rekening platform, langsung ditahan
     *    atas nama toko. Belum ada batas auto-release karena belum dikirim.
     */
    private function paidAndProcessing(User $buyer, Store $store): void
    {
        [$order, $storeOrder] = $this->placeOrder($buyer, $store);

        $payment = $this->settlePayment($order);

        $storeOrder->update(['status' => 'processing']);

        $this->holdEscrow($storeOrder, $payment);
    }

    /**
     * 3. Barang dikirim. Escrow masih ditahan penuh.
     */
    private function onTheWay(User $buyer, Store $store): void
    {
        [$order, $storeOrder] = $this->placeOrder($buyer, $store);

        $payment = $this->settlePayment($order);

        $storeOrder->update(['status' => 'shipped', 'shipped_at' => now()->subDays(2)]);

        Shipment::factory()->inTransit()->for($storeOrder)->create([
            'cost' => $storeOrder->shipping_cost,
        ]);

        $this->holdEscrow($storeOrder, $payment);
    }

    /**
     * 4. Barang sampai. Hitungan mundur pencairan otomatis mulai jalan, tapi
     *    pembeli masih punya waktu untuk protes.
     */
    private function awaitingBuyerConfirmation(User $buyer, Store $store): void
    {
        [$order, $storeOrder] = $this->placeOrder($buyer, $store);

        $payment = $this->settlePayment($order);

        $storeOrder->update([
            'status' => 'delivered',
            'shipped_at' => now()->subDays(3),
            'delivered_at' => now()->subDay(),
        ]);

        Shipment::factory()->delivered()->for($storeOrder)->create([
            'cost' => $storeOrder->shipping_cost,
        ]);

        $this->holdEscrow($storeOrder, $payment, now()->subDay()->addDays(self::AUTO_RELEASE_DAYS));
    }

    /**
     * 5. Pembeli diam sampai batas waktu lewat. Inilah baris yang dipungut
     *    scheduler auto-release pada jalannya berikutnya.
     */
    private function readyForAutoRelease(User $buyer, Store $store): void
    {
        [$order, $storeOrder] = $this->placeOrder($buyer, $store);

        $payment = $this->settlePayment($order);

        $storeOrder->update([
            'status' => 'delivered',
            'shipped_at' => now()->subDays(12),
            'delivered_at' => now()->subDays(9),
        ]);

        Shipment::factory()->delivered()->for($storeOrder)->create([
            'cost' => $storeOrder->shipping_cost,
            'shipped_at' => now()->subDays(12),
            'delivered_at' => now()->subDays(9),
        ]);

        $this->holdEscrow($storeOrder, $payment, now()->subDays(2));
    }

    /**
     * 6. Pembeli menekan "barang diterima": escrow cair ke wallet seller, komisi
     *    platform tercatat, lalu seller menarik saldonya ke rekening.
     */
    private function completedAndPaidOut(User $buyer, Store $store): void
    {
        [$order, $storeOrder] = $this->placeOrder($buyer, $store);

        $payment = $this->settlePayment($order);

        $storeOrder->update([
            'status' => 'completed',
            'shipped_at' => now()->subDays(14),
            'delivered_at' => now()->subDays(11),
            'buyer_confirmed_at' => now()->subDays(10),
            'completed_at' => now()->subDays(10),
        ]);

        Shipment::factory()->delivered()->for($storeOrder)->create([
            'cost' => $storeOrder->shipping_cost,
            'shipped_at' => now()->subDays(14),
            'delivered_at' => now()->subDays(11),
        ]);

        $order->update(['status' => 'completed']);

        $hold = $this->holdEscrow($storeOrder, $payment, now()->subDays(4));
        $this->releaseEscrow($hold, 'buyer_confirmed');
        $this->withdrawToBank($hold->sellerWallet);
    }

    /**
     * 7. Barang sampai tapi pembeli protes. Batas auto-release-nya SUDAH lewat,
     *    dan escrow tetap ngk cair — sengketa selalu menang atas batas waktu.
     */
    private function underDispute(User $buyer, Store $store): void
    {
        [$order, $storeOrder] = $this->placeOrder($buyer, $store);

        $payment = $this->settlePayment($order);

        $storeOrder->update([
            'status' => 'disputed',
            'shipped_at' => now()->subDays(11),
            'delivered_at' => now()->subDays(8),
        ]);

        Shipment::factory()->delivered()->for($storeOrder)->create([
            'cost' => $storeOrder->shipping_cost,
            'shipped_at' => now()->subDays(11),
            'delivered_at' => now()->subDays(8),
        ]);

        $this->holdEscrow($storeOrder, $payment, now()->subDay());

        Dispute::factory()->for($storeOrder)->create([
            'opened_by' => $buyer->id,
            'reason' => 'not_as_described',
            'description' => 'Warna yang datang beda jauh dari foto di katalog.',
            'status' => 'under_review',
        ]);
    }

    /**
     * 8. Sengketa dimenangkan pembeli: escrow dibatalkan, uangnya balik ke pembeli
     *    lewat saldo. Seller ngk pernah menerima sepeser pun.
     */
    private function refundedToWallet(User $buyer, Store $store): void
    {
        [$order, $storeOrder] = $this->placeOrder($buyer, $store);

        $payment = $this->settlePayment($order);

        $storeOrder->update([
            'status' => 'refunded',
            'shipped_at' => now()->subDays(20),
            'delivered_at' => now()->subDays(17),
        ]);

        $hold = $this->holdEscrow($storeOrder, $payment, now()->subDays(10));
        $hold->update([
            'status' => 'refunded',
            'refunded_amount' => $hold->gross_amount,
            'refunded_at' => now()->subDays(9),
        ]);

        // Dana batal jadi hak seller, jadi nilai tertahannya ikut dilepas.
        $hold->sellerWallet->decrement('balance_pending', (float) $hold->net_amount);

        $order->update(['status' => 'refunded']);

        $adminId = User::where('role', 'admin')->value('id');

        Dispute::factory()->resolvedForBuyer()->for($storeOrder)->create([
            'opened_by' => $buyer->id,
            'resolved_by' => $adminId,
            'reason' => 'wrong_item',
            'description' => 'Yang dikirim ukuran S, yang dipesan XL.',
        ]);

        $refund = Refund::factory()->toWallet()->completed()->create([
            'order_id' => $order->id,
            'store_order_id' => $storeOrder->id,
            'escrow_hold_id' => $hold->id,
            'amount' => $hold->gross_amount,
            'reason' => 'Sengketa dimenangkan pembeli.',
            'requested_by' => $buyer->id,
            'approved_by' => $adminId,
        ]);

        $this->postToWallet(
            wallet: $buyer->wallet,
            type: 'refund',
            direction: 'credit',
            amount: (float) $refund->amount,
            reference: $refund,
            idempotencyKey: 'refund:'.$refund->id,
            description: 'Pengembalian dana '.$refund->refund_number,
        );
    }

    // -----------------------------------------------------------------
    // Blok pembangun
    // -----------------------------------------------------------------

    /**
     * Membuat satu order beserta pesanan-tokonya, lengkap dengan salinan data
     * produk seperti yang dilakukan checkout sungguhan.
     *
     * @return array{0: Order, 1: StoreOrder}
     */
    private function placeOrder(User $buyer, Store $store): array
    {
        $variants = $this->pickVariants($store, 2);
        $address = $buyer->defaultAddress;

        $this->sequence++;
        $number = str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT);

        $lines = $variants->map(fn (ProductVariant $variant): array => [
            'variant' => $variant,
            'quantity' => 1,
            'unit_price' => (float) ($variant->price ?? $variant->product->base_price),
        ]);

        $subtotal = $lines->sum(fn (array $line): float => $line['unit_price'] * $line['quantity']);
        $shipping = 20000.0;
        $total = $subtotal + $shipping;
        $fee = round($total * self::PLATFORM_FEE_RATE, 2);

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.$number,
            'recipient_name' => $buyer->name,
            'recipient_phone' => $buyer->phone_number ?? '081200000000',
            'shipping_street' => $address?->street ?? 'Jl. Merdeka No. 1',
            'shipping_city' => $address?->city ?? 'Jakarta Pusat',
            'shipping_province' => $address?->province ?? 'DKI Jakarta',
            'shipping_postal_code' => $address?->postal_code ?? '10110',
            'subtotal' => $subtotal,
            'shipping_total' => $shipping,
            'grand_total' => $total,
            'status' => 'pending_payment',
            'placed_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $storeOrder = StoreOrder::create([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'store_order_number' => 'SO-'.now()->format('Ymd').'-'.$number,
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'total' => $total,
            'platform_fee_rate' => self::PLATFORM_FEE_RATE,
            'platform_fee_amount' => $fee,
            'seller_earning' => $total - $fee,
        ]);

        foreach ($lines as $line) {
            OrderItem::factory()
                ->fromVariant($line['variant'], $line['quantity'])
                ->for($storeOrder)
                ->create([
                    'recommended_size_label' => $line['variant']->size?->name,
                ]);
        }

        return [$order->refresh(), $storeOrder->refresh()];
    }

    private function createPayment(Order $order, string $status): Payment
    {
        return Payment::factory()
            ->when($status === 'paid', fn ($factory) => $factory->paid())
            ->for($order)
            ->create(['amount' => $order->grand_total]);
    }

    /**
     * Pembayaran berhasil, lengkap dengan catatan webhook-nya. `event_id` dari
     * provider inilah yang menahan notifikasi ganda diproses dua kali.
     */
    private function settlePayment(Order $order): Payment
    {
        $payment = $this->createPayment($order, 'paid');

        PaymentEvent::factory()->for($payment)->create([
            'event_id' => 'evt_'.$payment->reference,
            'event_type' => 'payment.settled',
        ]);

        $order->update(['status' => 'paid', 'paid_at' => now(), 'expires_at' => null]);

        return $payment;
    }

    /**
     * Menahan dana milik satu toko. Nilai pending di wallet seller ikut naik,
     * tapi saldo yang bisa ditarik belum bertambah sama sekali.
     */
    private function holdEscrow(StoreOrder $storeOrder, Payment $payment, ?\DateTimeInterface $autoReleaseAt = null): EscrowHold
    {
        $wallet = $storeOrder->store->owner->wallet;

        $hold = EscrowHold::create([
            'store_order_id' => $storeOrder->id,
            'payment_id' => $payment->id,
            'seller_wallet_id' => $wallet->id,
            'gross_amount' => $storeOrder->total,
            'platform_fee_amount' => $storeOrder->platform_fee_amount,
            'net_amount' => $storeOrder->seller_earning,
            'status' => 'held',
            'held_at' => now(),
            'auto_release_at' => $autoReleaseAt,
        ]);

        $wallet->increment('balance_pending', (float) $hold->net_amount);

        return $hold;
    }

    /**
     * Escrow cair: pending turun, saldo yang bisa ditarik naik. Komisi platform
     * ngk pernah muncul di wallet seller — sudah dipotong di `net_amount`, dan
     * angkanya tersimpan di store_orders + escrow_holds untuk pelaporan.
     */
    private function releaseEscrow(EscrowHold $hold, string $reason): void
    {
        $hold->update([
            'status' => 'released',
            'release_reason' => $reason,
            'released_at' => now(),
        ]);

        $wallet = $hold->sellerWallet;
        $wallet->decrement('balance_pending', (float) $hold->net_amount);

        $this->postToWallet(
            wallet: $wallet->refresh(),
            type: 'escrow_release',
            direction: 'credit',
            amount: (float) $hold->net_amount,
            reference: $hold,
            idempotencyKey: 'escrow-release:'.$hold->id,
            description: 'Pencairan '.$hold->storeOrder->store_order_number,
        );
    }

    /**
     * Seller menarik seluruh saldonya ke rekening bank.
     */
    private function withdrawToBank(Wallet $wallet): void
    {
        $wallet->refresh();
        $amount = (float) $wallet->balance_available;

        if ($amount <= 0) {
            return;
        }

        $account = PayoutAccount::where('user_id', $wallet->user_id)->firstOrFail();
        $fee = 6500.0;

        $payout = Payout::factory()->completed()->create([
            'user_id' => $wallet->user_id,
            'wallet_id' => $wallet->id,
            'payout_account_id' => $account->id,
            'account_bank_code' => $account->bank_code,
            'account_name' => $account->account_name,
            'account_number' => $account->account_number,
            'amount' => $amount,
            'fee_amount' => $fee,
            'net_amount' => $amount - $fee,
        ]);

        $this->postToWallet(
            wallet: $wallet,
            type: 'payout',
            direction: 'debit',
            amount: $amount,
            reference: $payout,
            idempotencyKey: 'payout:'.$payout->id,
            description: 'Penarikan ke '.$account->bank_code.' '.$account->account_number,
        );
    }

    /**
     * Satu-satunya pintu untuk mengubah saldo: catat di buku besar, baru
     * perbarui cache-nya. Di aplikasi sungguhan dua langkah ini dibungkus
     * DB::transaction dengan lockForUpdate.
     */
    private function postToWallet(
        Wallet $wallet,
        string $type,
        string $direction,
        float $amount,
        Model $reference,
        string $idempotencyKey,
        string $description,
    ): void {
        $current = (float) $wallet->balance_available;
        $balanceAfter = $direction === 'credit' ? $current + $amount : $current - $amount;

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'idempotency_key' => $idempotencyKey,
            'description' => $description,
        ]);

        $wallet->update(['balance_available' => $balanceAfter]);
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    private function pickVariants(Store $store, int $count): Collection
    {
        $variants = ProductVariant::with(['product', 'size', 'color'])
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('store_id', $store->id))
            ->orderBy('id')
            ->skip($this->variantOffset)
            ->take($count)
            ->get();

        $this->variantOffset += $count;

        if ($variants->count() < $count) {
            $this->variantOffset = 0;

            return ProductVariant::with(['product', 'size', 'color'])
                ->whereHas('product', fn ($query) => $query->where('store_id', $store->id))
                ->orderBy('id')
                ->take($count)
                ->get();
        }

        return $variants;
    }

    /**
     * @return Collection<int, User>
     */
    private function buyers(): Collection
    {
        return User::with(['defaultAddress', 'wallet'])
            ->where('role', 'user')
            ->orderBy('id')
            ->take(4)
            ->get();
    }
}
