<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'path',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Hanya boleh ada satu gambar utama per produk. Foto pertama sebuah produk
     * otomatis jadi gambar utama, dan menandai foto lain sebagai utama melepas
     * penanda dari foto yang lama.
     */
    protected static function booted(): void
    {
        static::creating(function (ProductImage $image): void {
            if (! $image->is_primary && ! static::where('product_id', $image->product_id)->where('is_primary', true)->exists()) {
                $image->is_primary = true;
            }
        });

        static::saved(function (ProductImage $image): void {
            if ($image->is_primary) {
                static::where('product_id', $image->product_id)
                    ->whereKeyNot($image->getKey())
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
        });
    }

    /**
     * Alamat foto untuk atribut `src`. `path` relatif ke folder `public/`, jadi
     * file unggahan di disk `public` disimpan dengan awalan `storage/`.
     *
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => asset($this->path));
    }

    // relationships

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
