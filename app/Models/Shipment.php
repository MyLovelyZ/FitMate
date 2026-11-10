<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Update status pengiriman yang boleh dilakukan seller setelah paket
     * diserahkan ke kurir: status sekarang => status berikutnya.
     *
     * - `in_transit` → `in_transit`: checkpoint baru (mis. "Tiba di gudang Bandung").
     * - `failed` = percobaan antar gagal; bisa dikirim ulang atau dikembalikan.
     * - `delivered` dan `returned` adalah akhir; lihat `StoreOrder::updateShipmentStatus()`.
     *
     * @var array<string, array<int, string>>
     */
    public const SELLER_TRANSITIONS = [
        'picked_up' => ['in_transit', 'delivered', 'failed'],
        'in_transit' => ['in_transit', 'delivered', 'failed'],
        'failed' => ['in_transit', 'returned'],
    ];

    /**
     * Keterangan bawaan di riwayat pelacakan kalau seller tidak menulis catatan.
     *
     * @var array<string, string>
     */
    public const DEFAULT_DESCRIPTIONS = [
        'in_transit' => 'Paket dalam perjalanan.',
        'delivered' => 'Paket sudah diterima di alamat tujuan.',
        'failed' => 'Paket gagal diantar.',
        'returned' => 'Paket dikembalikan ke penjual.',
    ];

    /**
     * Status pengiriman berikutnya yang boleh dipilih seller.
     *
     * @return array<string, string> status => label
     */
    public function nextStatuses(): array
    {
        return array_intersect_key(self::STATUS_LABELS, array_flip(self::SELLER_TRANSITIONS[$this->status] ?? []));
    }

    /**
     * Mencatat satu baris riwayat pelacakan.
     */
    public function record(string $status, string $description, ?User $by = null, ?string $location = null): ShipmentEvent
    {
        return $this->events()->create([
            'status' => $status,
            'description' => $description,
            'location' => filled($location) ? trim($location) : null,
            'recorded_by' => $by?->id,
        ]);
    }

    public function courierLabel(): string
    {
        foreach (self::OPTIONS as $option) {
            if ($option['courier'] === $this->courier && $option['service'] === $this->service) {
                return $option['label'];
            }
        }

        return strtoupper($this->courier).($this->service ? ' '.strtoupper($this->service) : '');
    }

    // relationships

    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }

    /**
     * Riwayat pelacakan, terbaru di atas.
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->latest()->latest('id');
    }
}
