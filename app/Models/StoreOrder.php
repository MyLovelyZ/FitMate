<?php

namespace App\Models;

use App\Enums\StoreOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

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
        'status',
        'note',
    ];

    /**
     * Transisi status yang sah. Selain yang terdaftar di sini ditolak.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => ['completed', 'refunded'],
        'completed' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StoreOrderStatus::class,
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $storeOrder): void {
            $storeOrder->store_order_number ??= 'SO-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        });
    }

    // Bagian Relationships

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

    // Bagian Helper

    public function canTransitionTo(StoreOrderStatus $status): bool
    {
        return in_array($status->value, self::ALLOWED_TRANSITIONS[$this->status->value] ?? [], true);
    }

    /**
     * @return list<StoreOrderStatus>
     */
    public function allowedNextStatuses(): array
    {
        return array_map(
            fn (string $value): StoreOrderStatus => StoreOrderStatus::from($value),
            self::ALLOWED_TRANSITIONS[$this->status->value] ?? [],
        );
    }

    /**
     * Pindahkan status, lalu turunkan ulang status order induknya.
     */
    public function transitionTo(StoreOrderStatus $status): void
    {
        $this->update(['status' => $status]);
        $this->order->syncStatusFromStoreOrders();
    }
}
