<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserBodyProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'profile_name',
        'gender',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $profile): void {
            if (! $profile->is_default) {
                return;
            }

            static::query()
                ->where('user_id', $profile->user_id)
                ->whereKeyNot($profile->getKey())
                ->update(['is_default' => false]);
        });
    }

    // Bagian Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(UserBodyMeasurement::class);
    }

    public function bodyMeasurements(): BelongsToMany
    {
        return $this->belongsToMany(BodyMeasurement::class, 'user_body_measurements')
            ->withPivot('value')
            ->withTimestamps();
    }

    public function sizeRecommendations(): HasMany
    {
        return $this->hasMany(SizeRecommendation::class);
    }

    // Bagian Helper

    /**
     * Nilai ukuran badan dipetakan berdasarkan `body_measurements.id`.
     *
     * @return array<int, float>
     */
    public function measurementValues(): array
    {
        return $this->measurements
            ->mapWithKeys(fn (UserBodyMeasurement $measurement): array => [
                $measurement->body_measurement_id => (float) $measurement->value,
            ])
            ->all();
    }

    public function hasMeasurements(): bool
    {
        return $this->measurements()->exists();
    }
}
