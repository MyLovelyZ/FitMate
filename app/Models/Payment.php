<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    /**
     * Provider pengganti selama KEP-3 (pilihan payment gateway) belum diputuskan.
     * Pembayaran ditandai lunas lewat tombol simulasi di halaman pesanan, tapi
     * jalurnya sama dengan webhook sungguhan: `settle()`.
     */
    public const SIMULATOR = 'simulator';

    /**
     * @var array<string, string>
     */
    public const METHOD_LABELS = [
        'qris' => 'QRIS',
        'virtual_account' => 'Virtual Account',
        'bank_transfer' => 'Transfer Bank',
        'ewallet' => 'E-Wallet',
        'credit_card' => 'Kartu Kredit',
    ];

    /**
     * Metode yang bisa dipilih di checkout.
     *
     * @var array<int, string>
     */
    public const CHECKOUT_METHODS = ['virtual_account', 'qris'];

    /**
     * @var array<string, string>
     */
    public const VA_BANKS = [
        'bca' => 'BCA',
        'bni' => 'BNI',
        'bri' => 'BRI',
        'mandiri' => 'Mandiri',
    ];

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

    /**
     * Membuat instruksi bayar (nomor VA atau kode QRIS) untuk order yang baru
     * dibuat. Batas waktunya sama dengan batas bayar order.
     */
    public static function issueFor(Order $order, string $method, ?string $vaBank = null): self
    {
        $reference = 'SIM-'.Str::upper(Str::random(12));
        $isVirtualAccount = $method === 'virtual_account';

        return $order->payments()->create([
            'method' => $method,
            'provider' => self::SIMULATOR,
            'reference' => $reference,
            'qr_string' => $method === 'qris' ? '00020101021226'.$reference : null,
            'va_bank' => $isVirtualAccount ? $vaBank : null,
            'va_number' => $isVirtualAccount ? '8808'.str_pad((string) $order->id, 10, '0', STR_PAD_LEFT) : null,
            'amount' => $order->grand_total,
            'status' => 'pending',
            'expires_at' => $order->expires_at,
        ]);
    }

    /**
     * Mencatat pembayaran lunas — titik masuk yang sama untuk webhook gateway
     * maupun tombol simulasi. Aman dipanggil berkali-kali: notifikasi dengan
     * `event_id` yang sama hanya diproses sekali (`payment_events` unique).
     *
     * Setelah lunas, order jadi `paid` dan dana tiap toko ditahan di escrow.
     *
     * @param  array<string, mixed>  $payload
     * @return bool true kalau notifikasi ini yang membuat pembayaran lunas.
     */
    public function settle(string $eventId, array $payload = []): bool
    {
        return DB::transaction(function () use ($eventId, $payload): bool {
            $payment = static::whereKey($this->id)->lockForUpdate()->firstOrFail();

            $event = PaymentEvent::firstOrCreate(
                ['provider' => $payment->provider, 'event_id' => $eventId],
                ['payment_id' => $payment->id, 'event_type' => 'payment.settled', 'payload' => $payload],
            );

            if ($event->processed_at !== null) {
                return false;
            }

            $event->update(['processed_at' => now()]);

            // Uang datang setelah order batal/kedaluwarsa: dicatat, tapi tidak
            // menghidupkan lagi order-nya.
            if ($payment->status !== 'pending' || $payment->isExpired() || $payment->order->status !== 'pending_payment') {
                return false;
            }

            $payment->update(['status' => 'paid', 'paid_at' => now(), 'expires_at' => null]);

            $order = $payment->order;
            $order->update(['status' => 'paid', 'paid_at' => now(), 'expires_at' => null]);

            foreach ($order->storeOrders()->with('store.owner')->get() as $storeOrder) {
                EscrowHold::holdFor($storeOrder, $payment);
            }

            $this->refresh();

            return true;
        });
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
