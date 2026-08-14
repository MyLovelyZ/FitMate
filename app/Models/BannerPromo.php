<?php

namespace App\Models;

use App\Enums\BannerPlacement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BannerPromo extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'link_url',
        'placement',
        'sort_order',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'placement' => BannerPlacement::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // Bagian Scope

    /**
     * Banner yang aktif dan sedang berada dalam rentang tanggal tayangnya.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActiveNow(Builder $query, ?BannerPlacement $placement = null): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $inner) => $inner->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $inner) => $inner->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->when($placement, fn (Builder $inner) => $inner->where('placement', $placement))
            ->orderBy('sort_order');
    }
}
