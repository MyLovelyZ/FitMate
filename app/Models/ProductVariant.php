<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'size_chart_entry_id',
        'sku',
        'color_name',
        'color_hex',
        'price',
        'compare_at_price',
        'stock',
        'weight_gram',
        'image',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // Bagian Scope

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    // Bagian Relationships

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sizeChartEntry(): BelongsTo
    {
        return $this->belongsTo(SizeChartEntry::class, 'size_chart_entry_id');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(ProductMeasurement::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Bagian Helper

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    public function sizeLabel(): ?string
    {
        return $this->sizeChartEntry?->label;
    }

    /**
     * Nama varian yang enak dibaca: "M · Hitam".
     */
    public function displayName(): string
    {
        return collect([$this->sizeLabel(), $this->color_name])
            ->filter()
            ->implode(' · ') ?: 'Standar';
    }

    public function shippingWeight(): int
    {
        return (int) ($this->weight_gram ?? $this->product?->weight_gram ?? 0);
    }
}
