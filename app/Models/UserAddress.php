<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'recipient_phone',
        'street',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $address): void {
            if (! $address->is_default) {
                return;
            }

            static::query()
                ->where('user_id', $address->user_id)
                ->whereKeyNot($address->getKey())
                ->update(['is_default' => false]);
        });
    }

    // Bagian Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Bagian Helper

    public function fullAddress(): string
    {
        return collect([$this->street, $this->city, $this->state, $this->postal_code, $this->country])
            ->filter()
            ->implode(', ');
    }
}
