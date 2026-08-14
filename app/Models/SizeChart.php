<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\SizeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SizeChart extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'size_type',
        'gender',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_type' => SizeType::class,
            'gender' => Gender::class,
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Bagian Scope

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Chart yang cocok untuk satu jenis ukuran dan gender.
     *
     * Chart unisex ikut dipertimbangkan karena alas kaki hanya punya satu chart
     * untuk semua gender.
     *
     * @param  Builder<self>  $query
     */
    public function scopeMatching(Builder $query, SizeType $sizeType, ?Gender $gender = null): void
    {
        $query->where('size_type', $sizeType)
            ->where(function (Builder $inner) use ($gender): void {
                $inner->where('gender', Gender::Unisex);

                if ($gender instanceof Gender) {
                    $inner->orWhere('gender', $gender);
                }
            })
            ->orderByRaw('CASE WHEN gender = ? THEN 0 ELSE 1 END', [$gender?->value ?? Gender::Unisex->value]);
    }

    // Bagian Relationships

    public function entries(): HasMany
    {
        return $this->hasMany(SizeChartEntry::class)->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function sizeRecommendations(): HasMany
    {
        return $this->hasMany(SizeRecommendation::class);
    }
}
