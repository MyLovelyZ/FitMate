<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ProductReview extends Model
{
    use HasFactory;

    /**
     * Umpan balik ukuran: nilai enum => label untuk pembeli.
     *
     * @var array<string, string>
     */
    public const FIT_LABELS = [
        'too_small' => 'Kekecilan',
        'fit' => 'Pas',
        'too_large' => 'Kebesaran',
    ];

    protected $fillable = [
        'order_item_id',
        'user_id',
        'product_id',
        'rating',
        'fit_feedback',
        'comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * Pembeli menilai barang yang sudah ia terima. Hanya pemilik pesanan, hanya
     * paket yang `completed`, dan sekali per barang (`order_item_id` unique).
     *
     * @throws ValidationException
     */
    public static function submitFor(OrderItem $item, User $buyer, int $rating, ?string $fitFeedback, ?string $comment): self
    {
        $item->loadMissing('storeOrder.order');

        $problem = match (true) {
            $item->storeOrder?->order?->user_id !== $buyer->id => 'Barang ini bukan dari pesananmu.',
            $item->storeOrder->status !== 'completed' => 'Ulasan bisa ditulis setelah pesanan selesai.',
            $item->product_id === null => 'Produk ini sudah tidak tersedia.',
            static::where('order_item_id', $item->id)->exists() => 'Barang ini sudah kamu ulas.',
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages(['review' => $problem]);
        }

        return static::create([
            'order_item_id' => $item->id,
            'user_id' => $buyer->id,
            'product_id' => $item->product_id,
            'rating' => $rating,
            'fit_feedback' => $fitFeedback,
            'comment' => filled($comment) ? trim($comment) : null,
        ]);
    }

    // relationships

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
