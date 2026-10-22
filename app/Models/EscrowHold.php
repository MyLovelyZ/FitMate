<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class EscrowHold extends Model
{
    use HasFactory;

    /**
     * Pembeli punya waktu sekian hari setelah paket sampai untuk protes; lewat
     * dari itu escrow cair otomatis.
     */
    public const AUTO_RELEASE_DAYS = 7;

    protected $fillable = [
        'store_order_id',
        'payment_id',
        'seller_wallet_id',
        'gross_amount',
        'platform_fee_amount',
        'net_amount',
        'refunded_amount',
        'status',
        'release_reason',
        'held_at',
        'auto_release_at',
        'released_at',
        'refunded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'platform_fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'held_at' => 'datetime',
            'auto_release_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * Boleh cair kalau masih ditahan, batas waktunya sudah lewat, dan ngk ada
     * sengketa yang menggantung. Sengketa selalu menang atas batas waktu.
     */
    public function isAutoReleasable(): bool
    {
        return $this->status === 'held'
            && $this->auto_release_at !== null
            && $this->auto_release_at->isPast()
            && ! $this->storeOrder->hasOpenDispute();
    }

    /**
     * Menahan dana satu toko setelah pembayaran masuk. Nilai pending di wallet
     * seller naik, saldo yang bisa ditarik belum. Aman dipanggil ulang: satu
     * paket toko hanya punya satu tahanan dana (`store_order_id` unique).
     */
    public static function holdFor(StoreOrder $storeOrder, Payment $payment): self
    {
        $wallet = Wallet::forUser($storeOrder->store->owner);

        $hold = static::firstOrCreate(['store_order_id' => $storeOrder->id], [
            'payment_id' => $payment->id,
            'seller_wallet_id' => $wallet->id,
            'gross_amount' => $storeOrder->total,
            'platform_fee_amount' => $storeOrder->platform_fee_amount,
            'net_amount' => $storeOrder->seller_earning,
            'status' => 'held',
            'held_at' => now(),
        ]);

        if ($hold->wasRecentlyCreated) {
            $wallet->adjustPending((float) $hold->net_amount);
        }

        return $hold;
    }

    /**
     * Dana cair ke seller: pending turun, saldo yang bisa ditarik naik lewat satu
     * baris buku besar. Komisi platform sudah dipotong di `net_amount`.
     */
    public function release(string $reason): void
    {
        DB::transaction(function () use ($reason): void {
            $status = static::whereKey($this->id)->lockForUpdate()->value('status');

            if ($status !== 'held') {
                return;
            }

            $this->update([
                'status' => 'released',
                'release_reason' => $reason,
                'released_at' => now(),
            ]);

            $this->sellerWallet->adjustPending(-(float) $this->net_amount);
            $this->sellerWallet->post(
                type: 'escrow_release',
                direction: 'credit',
                amount: (float) $this->net_amount,
                reference: $this,
                idempotencyKey: 'escrow-release:'.$this->id,
                description: 'Pencairan '.$this->storeOrder->store_order_number,
            );
        });
    }

    /**
     * Pesanan dibatalkan setelah dibayar: seluruh dana toko ini dikembalikan ke
     * saldo pembeli, seller tidak menerima apa pun.
     */
    public function refundToBuyer(User $requestedBy, string $reason): Refund
    {
        return DB::transaction(function () use ($requestedBy, $reason): Refund {
            $status = static::whereKey($this->id)->lockForUpdate()->value('status');

            if ($status !== 'held') {
                throw new LogicException('Dana paket '.$this->storeOrder->store_order_number.' sudah tidak ditahan.');
            }

            $this->update([
                'status' => 'refunded',
                'refunded_amount' => $this->gross_amount,
                'refunded_at' => now(),
            ]);

            $this->sellerWallet->adjustPending(-(float) $this->net_amount);

            $storeOrder = $this->storeOrder;

            $refund = Refund::create([
                'order_id' => $storeOrder->order_id,
                'store_order_id' => $storeOrder->id,
                'escrow_hold_id' => $this->id,
                'refund_number' => 'RF-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'amount' => $this->gross_amount,
                'destination' => 'wallet',
                'status' => 'completed',
                'reason' => $reason,
                'requested_by' => $requestedBy->id,
                'completed_at' => now(),
            ]);

            Wallet::forUser($storeOrder->order->user)->post(
                type: 'refund',
                direction: 'credit',
                amount: (float) $refund->amount,
                reference: $refund,
                idempotencyKey: 'refund:'.$refund->id,
                description: 'Pengembalian dana '.$storeOrder->store_order_number,
            );

            return $refund;
        });
    }

    /**
     * Dipanggil scheduler: cairkan semua escrow yang batas waktunya lewat dan
     * tidak sedang disengketakan, sekaligus menyelesaikan paketnya.
     *
     * @return int Jumlah paket yang diselesaikan.
     */
    public static function releaseDue(): int
    {
        $released = 0;

        static::query()
            ->where('status', 'held')
            ->where('auto_release_at', '<=', now())
            ->whereDoesntHave('storeOrder.disputes', fn ($query) => $query->whereIn('status', ['open', 'under_review']))
            ->with('storeOrder')
            ->eachById(function (self $hold) use (&$released): void {
                if ($hold->storeOrder->canTransitionTo('completed')) {
                    $hold->storeOrder->complete('auto_released');
                    $released++;
                }
            });

        return $released;
    }

    // relationships

    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function sellerWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'seller_wallet_id');
    }
}
