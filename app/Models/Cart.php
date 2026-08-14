<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
    ];

    // Bagian Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // Bagian Helper

    /**
     * Muat seluruh relasi yang dibutuhkan halaman keranjang dan checkout sekaligus,
     * supaya tidak ada query N+1 saat item dikelompokkan per toko.
     */
    public function loadItemsForDisplay(): self
    {
        return $this->load([
            'items.variant.sizeChartEntry',
            'items.variant.product.store',
            'items.variant.product.primaryImage',
        ]);
    }

    /**
     * Item keranjang dikelompokkan per toko — ini bentuk yang dipakai saat
     * checkout memecah keranjang jadi beberapa `store_orders`.
     *
     * @return SupportCollection<int, Collection<int, CartItem>>
     */
    public function itemsGroupedByStore(): SupportCollection
    {
        return $this->items->groupBy(fn (CartItem $item): int => $item->variant->product->store_id);
    }

    public function subtotal(): float
    {
        return (float) $this->items->sum(fn (CartItem $item): float => $item->subtotal());
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
