<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Status order dari sudut pandang pembeli: kunci tab => [label, nilai `status`].
     * Urutannya juga urutan tab di halaman "Pesanan Saya".
     *
     * @var array<string, array{0: string, 1: array<int, string>}>
     */
    public const BUYER_STATUS_GROUPS = [
        'belum-dibayar' => ['Belum Dibayar', ['pending_payment']],
        'diproses' => ['Diproses', ['paid', 'processing']],
        'dikirim' => ['Dalam Perjalanan', ['shipped']],
        'selesai' => ['Selesai', ['completed']],
        'dibatalkan' => ['Dibatalkan', ['cancelled', 'expired', 'refunded']],
    ];

    /**
     * Langkah progres yang ditampilkan ke pembeli, berurutan. Kelompok
     * `dibatalkan` sengaja tidak ada: pesanan batal keluar dari alur ini.
     *
     * @var array<int, string>
     */
    public const BUYER_STEPS = ['belum-dibayar', 'diproses', 'dikirim', 'selesai'];

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function buyerStatusGroup(): string
    {
        foreach (self::BUYER_STATUS_GROUPS as $group => [, $statuses]) {
            if (in_array($this->status, $statuses, true)) {
                return $group;
            }
        }

        return 'diproses';
    }

    public function buyerStatusLabel(): string
    {
        return match ($this->status) {
            'expired' => 'Kedaluwarsa',
            'refunded' => 'Dana Dikembalikan',
            default => self::BUYER_STATUS_GROUPS[$this->buyerStatusGroup()][0],
        };
    }

    /**
     * Posisi pesanan di `BUYER_STEPS`, atau null untuk pesanan yang batal,
     * kedaluwarsa, atau dananya dikembalikan.
     */
    public function buyerStepIndex(): ?int
    {
        $index = array_search($this->buyerStatusGroup(), self::BUYER_STEPS, true);

        return $index === false ? null : $index;
    }

    /**
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function inBuyerStatusGroup(Builder $query, string $group): void
    {
        $query->whereIn('status', self::BUYER_STATUS_GROUPS[$group][1] ?? []);
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
