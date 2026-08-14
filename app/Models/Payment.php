<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'method',
        'provider',
        'reference',
        'amount',
        'status',
        'paid_at',
        'expires_at',
        'payload',
    ];

    /**
     * `payload` berisi respon mentah provider dan bisa mengandung data sensitif —
     * jangan pernah ikut ke response atau ke view.
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
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'payload' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // Bagian Relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Bagian Helper

    public function isExpired(): bool
    {
        return $this->status === PaymentStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }
}
