<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_sku',
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
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * Apakah pembeli mengambil ukuran yang disarankan FitMate. Null kalau saat
     * checkout memang belum ada rekomendasinya.
     */
    public function followedSizeRecommendation(): ?bool
    {
        if ($this->recommended_size_label === null) {
            return null;
        }

        return $this->recommended_size_label === $this->size_label;
    }

    // relationships

    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
