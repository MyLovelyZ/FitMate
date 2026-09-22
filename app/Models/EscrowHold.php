<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscrowHold extends Model
{
    use HasFactory;

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
