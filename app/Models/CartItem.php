<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_variant_id',
        'quantity',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function lineTotal(): float
    {
        return (float) $this->productVariant->effective_price * $this->quantity;
    }

    /**
     * Alasan baris ini tidak bisa di-checkout, atau null kalau aman. Dipakai
     * untuk peringatan di halaman checkout dan dicek ulang di dalam transaksi
     * `Order::placeFromCart()` dengan stok yang sudah dikunci.
     */
    public function checkoutProblem(User $buyer): ?string
    {
        $variant = $this->productVariant;
        $product = $variant?->product;

        if ($variant === null || $product === null) {
            return 'Salah satu produk di keranjang sudah tidak tersedia.';
        }

        return match (true) {
            ! $variant->is_active || ! $product->isPurchasable() => "{$product->name} sedang tidak dijual.",
            $product->store?->user_id === $buyer->id => "{$product->name} adalah produk tokomu sendiri.",
            $variant->stock <= 0 => "{$product->name} sudah habis.",
            $variant->stock < $this->quantity => "Stok {$product->name} tinggal {$variant->stock}.",
            default => null,
        };
    }

    // relationships

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
