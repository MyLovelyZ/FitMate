<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'method',
        'provider',
        'reference',
        'qr_string',
        'qr_image_url',
        'va_bank',
        'va_number',
        'amount',
        'provider_fee',
        'status',
        'paid_at',
        'expires_at',
        'payload',
    ];

    /**
     * Respon mentah provider bisa memuat data sensitif, jadi ngk pernah ikut
     * ter-serialize keluar.
     *
     * @var list<string>
     */
    protected $hidden = [
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'provider_fee' => 'decimal:2',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function isExpired(): bool
    {
        return $this->status === 'pending'
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    // relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function escrowHolds(): HasMany
    {
        return $this->hasMany(EscrowHold::class);
    }
}
