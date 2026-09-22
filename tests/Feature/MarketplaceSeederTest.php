<?php

/**
 * Membuktikan data hasil seeding benar-benar memperagakan alur uang FitMate,
 * dan bahwa angka di wallet selalu cocok dengan buku besarnya.
 */

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Dispute;
use App\Models\EscrowHold;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('membuat setiap model jalur uang lewat factory-nya', function () {
    expect(Store::factory()->create())->toBeInstanceOf(Store::class)
        ->and(Cart::factory()->create())->toBeInstanceOf(Cart::class)
        ->and(CartItem::factory()->create())->toBeInstanceOf(CartItem::class)
        ->and(Order::factory()->create())->toBeInstanceOf(Order::class)
        ->and(StoreOrder::factory()->create())->toBeInstanceOf(StoreOrder::class)
        ->and(OrderItem::factory()->create())->toBeInstanceOf(OrderItem::class)
        ->and(Shipment::factory()->create())->toBeInstanceOf(Shipment::class)
        ->and(Payment::factory()->create())->toBeInstanceOf(Payment::class)
        ->and(PaymentEvent::factory()->create())->toBeInstanceOf(PaymentEvent::class)
        ->and(Wallet::factory()->create())->toBeInstanceOf(Wallet::class)
        ->and(WalletTransaction::factory()->create())->toBeInstanceOf(WalletTransaction::class)
        ->and(EscrowHold::factory()->create())->toBeInstanceOf(EscrowHold::class)
        ->and(PayoutAccount::factory()->create())->toBeInstanceOf(PayoutAccount::class)
        ->and(Payout::factory()->create())->toBeInstanceOf(Payout::class)
        ->and(Refund::factory()->create())->toBeInstanceOf(Refund::class)
        ->and(Dispute::factory()->create())->toBeInstanceOf(Dispute::class);
});

it('menyembunyikan respon mentah gateway dari serialisasi', function () {
    $payment = Payment::factory()->create(['payload' => ['card' => '4111']]);

    expect($payment->toArray())->not->toHaveKey('payload')
        ->and(Payout::factory()->create()->toArray())->not->toHaveKey('payload');
});

it('menghitung komisi platform sekali lalu menyimpannya', function () {
    $storeOrder = StoreOrder::factory()->create();

    $expectedFee = round((float) $storeOrder->total * (float) $storeOrder->platform_fee_rate, 2);

    expect((float) $storeOrder->platform_fee_amount)->toBe($expectedFee)
        ->and((float) $storeOrder->seller_earning)
        ->toBe((float) $storeOrder->total - (float) $storeOrder->platform_fee_amount);
});

describe('data hasil seeding', function () {
    beforeEach(function () {
        $this->seed();
    });

    it('memperagakan delapan tahap perjalanan uang', function () {
        expect(Order::count())->toBe(8)
            ->and(Payment::where('status', 'pending')->count())->toBe(1)
            ->and(Payment::where('status', 'paid')->count())->toBe(7)
            // Order pertama belum dibayar, jadi belum ada dana yang ditahan.
            ->and(EscrowHold::count())->toBe(7)
            ->and(EscrowHold::where('status', 'held')->count())->toBe(5)
            ->and(EscrowHold::where('status', 'released')->count())->toBe(1)
            ->and(EscrowHold::where('status', 'refunded')->count())->toBe(1)
            ->and(Dispute::count())->toBe(2)
            ->and(Payout::where('status', 'completed')->count())->toBe(1)
            ->and(Refund::where('status', 'completed')->count())->toBe(1);
    });

    it('memberi setiap webhook pembayaran catatannya sendiri', function () {
        expect(PaymentEvent::count())->toBe(7)
            ->and(PaymentEvent::distinct()->count('event_id'))->toBe(7);
    });

    it('menyalin nama produk dan ukuran ke tiap baris nota', function () {
        $item = OrderItem::firstOrFail();

        expect($item->product_name)->not->toBeEmpty()
            ->and($item->variant_sku)->not->toBeEmpty()
            ->and((float) $item->subtotal)->toBe((float) $item->unit_price * $item->quantity)
            ->and(OrderItem::whereNull('product_name')->count())->toBe(0);
    });

    it('menjaga saldo tiap wallet sama dengan buku besarnya', function () {
        Wallet::with('transactions')->each(function (Wallet $wallet): void {
            expect($wallet->isBalanced())->toBeTrue(
                "wallet {$wallet->id} melenceng: cache {$wallet->balance_available}, ledger {$wallet->ledgerBalance()}"
            );
        });
    });

    it('menyamakan total dana tertahan dengan escrow yang masih held', function () {
        $pendingCache = (float) Wallet::sum('balance_pending');
        $heldTotal = (float) EscrowHold::where('status', 'held')->sum('net_amount');

        expect($pendingCache)->toBe($heldTotal);
    });

    it('mencairkan escrow ke wallet seller lalu menariknya ke rekening', function () {
        $released = EscrowHold::where('status', 'released')->firstOrFail();
        $wallet = $released->sellerWallet;

        $credit = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', 'escrow_release')
            ->firstOrFail();

        $debit = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', 'payout')
            ->firstOrFail();

        expect($released->release_reason)->toBe('buyer_confirmed')
            ->and((float) $credit->amount)->toBe((float) $released->net_amount)
            ->and($credit->direction)->toBe('credit')
            ->and($debit->direction)->toBe('debit')
            // Ditarik habis, jadi saldonya kembali nol tapi jejaknya tetap ada.
            ->and((float) $wallet->fresh()->balance_available)->toBe(0.0)
            ->and($wallet->fresh()->transactions()->count())->toBe(2);
    });

    it('menahan pencairan otomatis selama sengketanya belum selesai', function () {
        $overdue = EscrowHold::with('storeOrder')
            ->where('status', 'held')
            ->whereNotNull('auto_release_at')
            ->where('auto_release_at', '<', now())
            ->get();

        expect($overdue)->toHaveCount(2);

        $releasable = $overdue->filter(fn (EscrowHold $hold): bool => $hold->isAutoReleasable());
        $blocked = $overdue->reject(fn (EscrowHold $hold): bool => $hold->isAutoReleasable());

        expect($releasable)->toHaveCount(1)
            ->and($blocked)->toHaveCount(1)
            ->and($blocked->first()->storeOrder->status)->toBe('disputed')
            ->and($blocked->first()->storeOrder->hasOpenDispute())->toBeTrue();
    });

    it('mengembalikan dana ke saldo pembeli tanpa pernah menyentuh seller', function () {
        $refund = Refund::where('destination', 'wallet')->firstOrFail();
        $buyerWallet = $refund->requester->wallet;

        $entry = WalletTransaction::where('wallet_id', $buyerWallet->id)
            ->where('type', 'refund')
            ->firstOrFail();

        expect($refund->escrowHold->status)->toBe('refunded')
            ->and((float) $entry->amount)->toBe((float) $refund->amount)
            ->and($entry->direction)->toBe('credit')
            ->and((float) $buyerWallet->balance_available)->toBe((float) $refund->amount)
            // Wallet seller-nya ngk pernah dikredit dari escrow ini.
            ->and(WalletTransaction::where('reference_id', $refund->escrow_hold_id)
                ->where('reference_type', $refund->escrowHold->getMorphClass())
                ->count())->toBe(0);
    });

    it('memberi wallet ke semua user dan rekening pencairan ke tiap seller', function () {
        expect(Wallet::count())->toBe(User::count())
            ->and(PayoutAccount::count())->toBe(User::where('role', 'seller')->count());
    });

    it('aman dijalankan ulang tanpa menggandakan order', function () {
        $this->seed();

        expect(Order::count())->toBe(8)
            ->and(EscrowHold::count())->toBe(7);
    });
});
