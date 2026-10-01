<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StoreOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'store_id',
        'store_order_number',
        'subtotal',
        'shipping_cost',
        'discount',
        'total',
        'platform_fee_rate',
        'platform_fee_amount',
        'seller_earning',
        'status',
        'shipped_at',
        'delivered_at',
        'buyer_confirmed_at',
        'completed_at',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'platform_fee_rate' => 'decimal:4',
            'platform_fee_amount' => 'decimal:2',
            'seller_earning' => 'decimal:2',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'buyer_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Sengketa yang masih terbuka menahan pencairan escrow, walaupun batas
     * auto-release-nya sudah lewat.
     */
    public function hasOpenDispute(): bool
    {
        return $this->disputes()
            ->whereIn('status', ['open', 'under_review'])
            ->exists();
    }

    // relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function escrowHold(): HasOne
    {
        return $this->hasOne(EscrowHold::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }
}
