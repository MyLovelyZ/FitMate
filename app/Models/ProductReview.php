<?php

namespace App\Models;

use App\Enums\FitFeedback;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'order_item_id',
        'rating',
        'comment',
        'fit_feedback',
        'size_purchased',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fit_feedback' => FitFeedback::class,
            'is_verified_purchase' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $review): void {
            $review->is_verified_purchase = $review->order_item_id !== null;
        });

        static::saved(fn (self $review) => $review->product?->recalculateRating());
        static::deleted(fn (self $review) => $review->product?->recalculateRating());
    }

    // Bagian Relationships

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
