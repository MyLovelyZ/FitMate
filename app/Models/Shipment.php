<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'pending' => 'Menunggu Dijemput Kurir',
        'picked_up' => 'Sudah Dijemput Kurir',
        'in_transit' => 'Dalam Perjalanan',
        'delivered' => 'Sudah Diterima',
        'failed' => 'Gagal Dikirim',
        'returned' => 'Dikembalikan ke Penjual',
    ];

    /**
     * Pilihan pengiriman di checkout, berlaku untuk setiap paket toko.
     *
     * Masih tarif flat per paket — KEP-4 (ongkir lewat API kurir atau flat) belum
     * diputuskan. Kalau nanti pakai API kurir, `cost` di sini diganti hasil hitungan.
     *
     * @var array<string, array{courier: string, service: string, label: string, eta: string, cost: int}>
     */
    public const OPTIONS = [
        'jne-reg' => ['courier' => 'jne', 'service' => 'reg', 'label' => 'JNE Reguler', 'eta' => '3–5 hari', 'cost' => 20000],
        'jnt-ez' => ['courier' => 'jnt', 'service' => 'ez', 'label' => 'J&T EZ', 'eta' => '2–4 hari', 'cost' => 22000],
        'sicepat-best' => ['courier' => 'sicepat', 'service' => 'best', 'label' => 'SiCepat BEST', 'eta' => '1–2 hari', 'cost' => 35000],
    ];

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
            'cost' => 'decimal:2',
            'weight_gram' => 'integer',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    // relationships

    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }
}
