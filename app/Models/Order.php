<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'recipient_name',
        'recipient_phone',
        'shipping_street',
        'shipping_city',
        'shipping_province',
        'shipping_postal_code',
        'shipping_country',
        'subtotal',
        'shipping_total',
        'discount_total',
        'grand_total',
        'status',
        'note',
        'placed_at',
        'paid_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    // relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function storeOrders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    /**
     * @return HasManyThrough<OrderItem, StoreOrder, $this>
     */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, StoreOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function successfulPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', 'paid');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
