<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_purchase',
        'max_discount',
        'usage_limit',
        'starts_at',
        'expiry_date',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'starts_at' => 'datetime',
            'expiry_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // Bagian Scope

    /**
     * Kupon yang aktif, sedang dalam rentang tanggalnya, dan kuotanya belum habis.
     *
     * @param  Builder<self>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $inner) => $inner->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $inner) => $inner->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString()))
            ->where(fn (Builder $inner) => $inner->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'));
    }

    // Bagian Relationships

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    // Bagian Helper

    /**
     * Potongan untuk satu subtotal. Tipe persentase dibatasi `max_discount`,
     * dan potongan tidak pernah melebihi subtotal itu sendiri.
     */
    public function calculateDiscount(float $subtotal): float
    {
        $discount = match ($this->type) {
            CouponType::Percentage => $subtotal * ((float) $this->value / 100),
            CouponType::Fixed => (float) $this->value,
        };

        if ($this->type === CouponType::Percentage && $this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return round(min($discount, $subtotal), 2);
    }

    public function meetsMinimumPurchase(float $subtotal): bool
    {
        return $this->min_purchase === null || $subtotal >= (float) $this->min_purchase;
    }

    public function usedBy(User $user): bool
    {
        return $this->usages()->where('user_id', $user->id)->exists();
    }
}
