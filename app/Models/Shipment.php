<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_order_id',
        'courier',
        'service',
        'tracking_number',
        'cost',
        'weight_gram',
        'status',
        'shipped_at',
        'delivered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'cost' => 'decimal:2',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    // Bagian Relationships

    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }
}
