<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'slug',
        'description',
        'gender',
        'base_price',
        'compare_at_price',
        'is_active',
        'banned_at',
        'banned_by',
        'ban_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'is_active' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    /**
     * Boleh dilihat dan dibeli pembeli: produknya aktif, tidak diblokir
     * superadmin, dan tokonya sedang berjualan (bukan pending, ditolak, atau
     * ditangguhkan).
     */
    public function isPurchasable(): bool
    {
        return $this->is_active && ! $this->isBanned() && (bool) $this->store?->canSell();
    }

    /**
     * Versi query dari `isPurchasable()` untuk katalog dan halaman toko.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function purchasable(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereNull('banned_at')
            ->whereHas('store', fn (Builder $store) => $store->selling());
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * Superadmin menurunkan produk dari katalog. Terpisah dari `is_active` milik
     * seller, jadi seller tidak bisa menayangkannya lagi sendiri.
     */
    public function ban(User $by, string $reason): void
    {
        $this->update([
            'banned_at' => now(),
            'banned_by' => $by->id,
            'ban_reason' => $reason,
        ]);
    }

    public function unban(): void
    {
        $this->update([
            'banned_at' => null,
            'banned_by' => null,
            'ban_reason' => null,
        ]);
    }

    /**
     * Diskon ditandai lewat harga coret yang lebih tinggi dari harga jual.
     */
    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null
            && (float) $this->compare_at_price > (float) $this->base_price;
    }

    // relationships

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Galeri foto, gambar utama selalu di depan.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Foto yang mewakili produk di daftar (keranjang, tabel seller).
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }
}
