<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBodyMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_body_profile_id',
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

    public function bodyProfile(): BelongsTo
    {
        return $this->belongsTo(UserBodyProfile::class, 'user_body_profile_id');
    }

    public function bodyMeasurement(): BelongsTo
    {
        return $this->belongsTo(BodyMeasurement::class);
    }
}
