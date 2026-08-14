<?php

namespace App\Models;

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
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $image): void {
            if (! $image->is_primary) {
                return;
            }

            static::query()
                ->where('product_id', $image->product_id)
                ->whereKeyNot($image->getKey())
                ->update(['is_primary' => false]);
        });
    }

    // Bagian Relationships

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Bagian Helper

    public function url(): string
    {
        return str_starts_with($this->path, 'http')
            ? $this->path
            : asset('storage/'.ltrim($this->path, '/'));
    }
}
