<?php

namespace App\Models;

use App\Enums\StoreStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'logo',
        'banner',
        'phone_number',
        'email',
        'street',
        'city',
        'state',
        'postal_code',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'verified_at' => 'datetime',
            'rating_average' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Bagian Scope

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', StoreStatus::Active);
    }

    // Bagian Relationships

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function storeOrders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    // Bagian Helper

    public function isActive(): bool
    {
        return $this->status === StoreStatus::Active;
    }

    /**
     * Hitung ulang rating toko dari seluruh review produknya.
     */
    public function recalculateRating(): void
    {
        $aggregate = ProductReview::query()
            ->whereIn('product_id', $this->products()->withTrashed()->select('id'))
            ->selectRaw('COUNT(*) as total, COALESCE(AVG(rating), 0) as average')
            ->first();

        $this->forceFill([
            'rating_count' => (int) ($aggregate->total ?? 0),
            'rating_average' => round((float) ($aggregate->average ?? 0), 2),
        ])->save();
    }
}
