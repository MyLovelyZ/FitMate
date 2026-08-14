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

    // Bagian Relationships

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    // Bagian Helper

    public function subtotal(): float
    {
        return (float) $this->variant->price * $this->quantity;
    }

    public function exceedsStock(): bool
    {
        return $this->quantity > $this->variant->stock;
    }
}
