<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'size_label',
        'color_name',
        'unit_price',
        'quantity',
        'subtotal',
        'recommended_size_label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    // Bagian Relationships

    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class);
    }

    // Bagian Helper

    /**
     * Pembeli mengikuti ukuran yang disarankan FitMate atau tidak.
     * Null berarti saat itu tidak ada rekomendasi yang bisa dihitung.
     */
    public function followedRecommendation(): ?bool
    {
        if ($this->recommended_size_label === null || $this->size_label === null) {
            return null;
        }

        return $this->recommended_size_label === $this->size_label;
    }
}
