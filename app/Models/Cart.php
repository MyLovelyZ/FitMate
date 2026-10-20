<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
    ];

    /**
     * Keranjang milik pembeli; dibuat saat pertama kali menambah barang.
     */
    public static function forUser(User $user): self
    {
        return static::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Menambah varian ke keranjang. Jumlah di keranjang tidak boleh melebihi
     * stok varian, jadi yang benar-benar ditambahkan bisa lebih kecil dari
     * `$quantity` — nilai itulah yang dikembalikan (0 kalau stoknya sudah habis
     * terpakai di keranjang).
     */
    public function add(ProductVariant $variant, int $quantity = 1): int
    {
        return DB::transaction(function () use ($variant, $quantity): int {
            $item = $this->items()
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            $added = max(0, min($quantity, $variant->stock - ($item->quantity ?? 0)));

            if ($added === 0) {
                return 0;
            }

            if ($item) {
                $item->increment('quantity', $added);
            } else {
                $this->items()->create(['product_variant_id' => $variant->id, 'quantity' => $added]);
            }

            return $added;
        });
    }

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
