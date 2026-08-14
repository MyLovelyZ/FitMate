<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SizeChartEntryMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'size_chart_entry_id',
        'body_measurement_id',
        'min_value',
        'max_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
        ];
    }

    // Bagian Relationships

    public function entry(): BelongsTo
    {
        return $this->belongsTo(SizeChartEntry::class, 'size_chart_entry_id');
    }

    public function bodyMeasurement(): BelongsTo
    {
        return $this->belongsTo(BodyMeasurement::class);
    }

    // Bagian Helper

    public function contains(float $value): bool
    {
        return $value >= (float) $this->min_value && $value <= (float) $this->max_value;
    }
}
