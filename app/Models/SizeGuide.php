<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SizeGuide extends Model
{
    use HasFactory;

    protected $fillable = [
        'size_id',
        'measurement_key',
        'label',
        'unit',
        'min_value',
        'max_value',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    // relationships

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }
}
