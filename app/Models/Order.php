<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\StoreOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'coupon_id',
        'recipient_name',
        'recipient_phone',
        'shipping_street',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'subtotal',
        'shipping_total',
        'discount_total',
        'grand_total',
        'status',
        'note',
        'placed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'placed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->order_number ??= static::generateOrderNumber();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public static function generateOrderNumber(): string
    {
        return 'FM-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    // Bagian Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function storeOrders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, StoreOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): ?Payment
    {
        return $this->payments()->latest('id')->first();
    }

    // Bagian Helper

    public function shippingAddress(): string
    {
        return collect([
            $this->shipping_street,
            $this->shipping_city,
            $this->shipping_state,
            $this->shipping_postal_code,
            $this->shipping_country,
        ])->filter()->implode(', ');
    }

    /**
     * Status order induk diturunkan dari gabungan status seluruh `store_orders`.
     *
     * Selama masih menunggu pembayaran, status induk tidak ikut bergerak —
     * seller belum boleh memproses apa pun sebelum pembayaran masuk.
     */
    public function syncStatusFromStoreOrders(): void
    {
        if ($this->status === OrderStatus::PendingPayment) {
            return;
        }

        $statuses = $this->storeOrders()->pluck('status');

        if ($statuses->isEmpty()) {
            return;
        }

        $derived = match (true) {
            $statuses->every(fn (StoreOrderStatus $status): bool => $status === StoreOrderStatus::Cancelled) => OrderStatus::Cancelled,
            $statuses->every(fn (StoreOrderStatus $status): bool => $status === StoreOrderStatus::Refunded) => OrderStatus::Refunded,
            $statuses->every(fn (StoreOrderStatus $status): bool => in_array($status, [StoreOrderStatus::Completed, StoreOrderStatus::Delivered, StoreOrderStatus::Cancelled], true)) => OrderStatus::Completed,
            $statuses->contains(StoreOrderStatus::Shipped) => OrderStatus::Shipped,
            $statuses->contains(StoreOrderStatus::Processing) => OrderStatus::Processing,
            default => OrderStatus::Paid,
        };

        if ($derived !== $this->status) {
            $this->update(['status' => $derived]);
        }
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [OrderStatus::PendingPayment, OrderStatus::Paid], true);
    }
}
