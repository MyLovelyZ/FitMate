<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'body_measurement_id',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
    }

    // Bagian Relationships

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function bodyMeasurement(): BelongsTo
    {
        return $this->belongsTo(BodyMeasurement::class);
    }
}
