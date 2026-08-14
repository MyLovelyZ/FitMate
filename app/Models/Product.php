<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'category_id',
        'brand_id',
        'size_chart_id',
        'name',
        'slug',
        'description',
        'target_gender',
        'base_price',
        'weight_gram',
        'status',
        'is_featured',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'target_gender' => Gender::class,
            'base_price' => 'decimal:2',
            'rating_average' => 'decimal:2',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
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
        $query->where('status', ProductStatus::Active);
    }

    /**
     * Produk yang benar-benar boleh tampil di katalog publik.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Active)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    // Bagian Relationships

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(SizeChart::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    // Bagian Helper

    public function requiresSizing(): bool
    {
        return $this->size_chart_id !== null;
    }

    public function isActive(): bool
    {
        return $this->status === ProductStatus::Active;
    }

    /**
     * Harga terendah dari varian aktif, jatuh ke `base_price` kalau belum ada varian.
     */
    public function displayPrice(): float
    {
        $lowest = $this->relationLoaded('variants')
            ? $this->variants->where('is_active', true)->min('price')
            : $this->variants()->where('is_active', true)->min('price');

        return (float) ($lowest ?? $this->base_price);
    }

    public function totalStock(): int
    {
        return (int) $this->variants()->where('is_active', true)->sum('stock');
    }

    /**
     * Hitung ulang cache rating produk, lalu ikut menyegarkan rating tokonya.
     */
    public function recalculateRating(): void
    {
        $aggregate = $this->reviews()
            ->selectRaw('COUNT(*) as total, COALESCE(AVG(rating), 0) as average')
            ->first();

        $this->forceFill([
            'rating_count' => (int) ($aggregate->total ?? 0),
            'rating_average' => round((float) ($aggregate->average ?? 0), 2),
        ])->save();

        $this->store?->recalculateRating();
    }
}
