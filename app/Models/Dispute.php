<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_order_id',
        'opened_by',
        'reason',
        'description',
        'status',
        'resolved_by',
        'resolution_note',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'under_review'], true);
    }

    // relationships

    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
