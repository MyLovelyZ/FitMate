<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
    ];

    /**
     * Isi keranjang boleh campur beberapa toko; pengelompokannya baru terjadi
     * saat checkout, jadi ini cuma bantuan tampilan.
     *
     * @return Collection<int, \Illuminate\Database\Eloquent\Collection<int, CartItem>>
     */
    public function itemsGroupedByStore(): Collection
    {
        return $this->items
            ->loadMissing('productVariant.product')
            ->groupBy(fn (CartItem $item): int => $item->productVariant->product->store_id);
    }

    // relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
