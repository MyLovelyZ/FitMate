<?php

namespace App\Models;

use App\Enums\MeasurementScope;
use App\Enums\SizeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodyMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'description',
        'unit',
        'applies_to',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applies_to' => MeasurementScope::class,
            'is_active' => 'boolean',
        ];
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
     * Dimensi yang relevan untuk satu jenis ukuran, plus dimensi `general`
     * (tinggi dan berat badan) yang selalu ikut dihitung.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForSizeType(Builder $query, SizeType $sizeType): void
    {
        $scope = MeasurementScope::tryFrom($sizeType->value);

        $query->where(function (Builder $inner) use ($scope): void {
            $inner->where('applies_to', MeasurementScope::General);

            if ($scope instanceof MeasurementScope) {
                $inner->orWhere('applies_to', $scope);
            }
        })->orderBy('sort_order');
    }

    // Bagian Relationships

    public function userMeasurements(): HasMany
    {
        return $this->hasMany(UserBodyMeasurement::class);
    }

    public function sizeChartEntryMeasurements(): HasMany
    {
        return $this->hasMany(SizeChartEntryMeasurement::class);
    }

    public function productMeasurements(): HasMany
    {
        return $this->hasMany(ProductMeasurement::class);
    }
}
