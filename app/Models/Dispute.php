<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

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

    /**
     * Alasan komplain yang bisa dipilih pembeli.
     *
     * @var array<string, string>
     */
    public const REASON_LABELS = [
        'not_received' => 'Barang tidak sampai',
        'not_as_described' => 'Tidak sesuai deskripsi',
        'damaged' => 'Barang rusak',
        'wrong_item' => 'Barang / ukuran salah kirim',
        'other' => 'Lainnya',
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'open' => 'Baru',
        'under_review' => 'Ditinjau',
        'resolved_buyer' => 'Selesai (dana ke pembeli)',
        'resolved_seller' => 'Selesai (dana ke penjual)',
        'cancelled' => 'Dibatalkan',
    ];

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'under_review'], true);
    }

    /**
     * CS mulai menangani tiket: pembeli melihat statusnya "Ditinjau".
     */
    public function markUnderReview(): void
    {
        if ($this->status === 'open') {
            $this->update(['status' => 'under_review']);
        }
    }

    /**
     * CS memutuskan sengketa. `buyer` = dana paket dikembalikan ke saldo pembeli
     * (paket `refunded`); `seller` = paket diselesaikan dan dana dicairkan ke
     * penjual (`dispute_resolved`). Keduanya dalam satu transaksi.
     *
     * @param  'buyer'|'seller'  $winner
     *
     * @throws LogicException kalau sengketa sudah selesai.
     */
    public function resolve(User $staff, string $winner, string $note): void
    {
        DB::transaction(function () use ($staff, $winner, $note): void {
            $status = static::whereKey($this->id)->lockForUpdate()->value('status');

            if (! in_array($status, ['open', 'under_review'], true)) {
                throw new LogicException('Sengketa ini sudah diputuskan.');
            }

            $this->update([
                'status' => $winner === 'buyer' ? 'resolved_buyer' : 'resolved_seller',
                'resolved_by' => $staff->id,
                'resolution_note' => $note,
                'resolved_at' => now(),
            ]);

            if ($winner === 'buyer') {
                $this->storeOrder->refundForDispute($staff, 'Komplain disetujui CS: '.$note);
            } else {
                $this->storeOrder->complete('dispute_resolved');
            }
        });
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
