<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class StoreOrder extends Model
{
    use HasFactory;

    /**
     * Komisi platform, dikunci ke `platform_fee_rate` saat checkout.
     */
    public const PLATFORM_FEE_RATE = 0.05;

    /**
     * Transisi status yang sah: status sekarang => status berikutnya. Yang tidak
     * ada di sini ditolak `transitionTo()`.
     *
     * - `shipped` boleh langsung `completed`: pembeli menekan "Pesanan Diterima"
     *   sebelum kurir menandai sampai.
     * - `disputed` diselesaikan admin/CS (Fase 8), bukan dari halaman ini.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'completed'],
        'delivered' => ['completed', 'disputed'],
        'disputed' => ['completed', 'refunded'],
    ];

    protected $fillable = [
        'order_id',
        'store_id',
        'store_order_number',
        'subtotal',
        'shipping_cost',
        'discount',
        'total',
        'platform_fee_rate',
        'platform_fee_amount',
        'seller_earning',
        'status',
        'shipped_at',
        'delivered_at',
        'buyer_confirmed_at',
        'completed_at',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'platform_fee_rate' => 'decimal:4',
            'platform_fee_amount' => 'decimal:2',
            'seller_earning' => 'decimal:2',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'buyer_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Status paket per toko seperti yang dibaca pembeli.
     *
     * @var array<string, string>
     */
    public const BUYER_STATUS_LABELS = [
        'pending' => 'Menunggu Diproses Penjual',
        'processing' => 'Sedang Dikemas',
        'shipped' => 'Dalam Perjalanan',
        'delivered' => 'Sudah Sampai',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'refunded' => 'Dana Dikembalikan',
        'disputed' => 'Dalam Komplain',
    ];

    /**
     * Sengketa yang masih terbuka menahan pencairan escrow, walaupun batas
     * auto-release-nya sudah lewat.
     */
    public function hasOpenDispute(): bool
    {
        return $this->disputes()
            ->whereIn('status', ['open', 'under_review'])
            ->exists();
    }

    /**
     * Membuat paket satu toko dari baris keranjangnya: data produk disalin ke
     * `order_items` (nota tidak ikut berubah kalau seller mengubah harga besok),
     * stok varian dikurangi, dan pengiriman disiapkan dengan kurir pilihan pembeli.
     *
     * Dipanggil dari `Order::placeFromCart()` di dalam transaksinya; varian
     * sudah dikunci dan sudah dicek stoknya di sana.
     *
     * `$discount` = bagian diskon voucher untuk paket ini. Total paket (dasar
     * komisi, escrow, dan refund) adalah yang benar-benar dibayar pembeli.
     *
     * @param  Collection<int, CartItem>  $items
     */
    public static function openFor(Order $order, Collection $items, string $shippingOption, float $discount = 0): self
    {
        $shipping = Shipment::OPTIONS[$shippingOption];
        $subtotal = $items->sum(fn (CartItem $item): float => $item->lineTotal());
        $total = $subtotal + $shipping['cost'] - $discount;
        $fee = round($total * self::PLATFORM_FEE_RATE, 2);

        $storeOrder = $order->storeOrders()->create([
            'store_id' => $items->first()->productVariant->product->store_id,
            'store_order_number' => Order::newNumber('SO'),
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping['cost'],
            'discount' => $discount,
            'total' => $total,
            'platform_fee_rate' => self::PLATFORM_FEE_RATE,
            'platform_fee_amount' => $fee,
            'seller_earning' => $total - $fee,
            'status' => 'pending',
        ]);

        foreach ($items as $item) {
            $variant = $item->productVariant;

            // `recommended_size_label` masih kosong sampai rekomendasi ukuran (BE-062) jadi.
            $storeOrder->items()->create([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_sku' => $variant->sku,
                'size_label' => $variant->size?->name,
                'color_name' => $variant->color?->name,
                'unit_price' => $variant->effective_price,
                'quantity' => $item->quantity,
                'subtotal' => $item->lineTotal(),
            ]);

            $variant->decrement('stock', $item->quantity);
        }

        $storeOrder->shipment()->create([
            'courier' => $shipping['courier'],
            'service' => $shipping['service'],
            'cost' => $shipping['cost'],
            'status' => 'pending',
        ]);

        return $storeOrder;
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Paket dari order yang belum dibayar tidak boleh diproses seller.
     */
    public function isAwaitingPayment(): bool
    {
        return ! $this->order->isPaid();
    }

    /**
     * Seller mulai mengemas pesanan yang sudah dibayar.
     */
    public function process(): void
    {
        if ($this->isAwaitingPayment()) {
            throw new LogicException('Pesanan '.$this->store_order_number.' belum dibayar.');
        }

        $this->transitionTo('processing');
    }

    /**
     * Seller menyerahkan paket ke kurir dan mengisi nomor resi.
     */
    public function ship(string $trackingNumber): void
    {
        $this->transitionTo('shipped', ['shipped_at' => now()], function () use ($trackingNumber): void {
            $default = Shipment::OPTIONS[array_key_first(Shipment::OPTIONS)];
            $shipment = $this->shipment()->firstOrNew([], [
                'courier' => $default['courier'],
                'service' => $default['service'],
                'cost' => $this->shipping_cost,
            ]);

            $shipment->fill([
                'tracking_number' => $trackingNumber,
                'status' => 'in_transit',
                'shipped_at' => now(),
            ])->save();
        });
    }

    /**
     * Kurir menandai paket sampai (sementara ditekan seller selama belum ada
     * integrasi kurir). Hitungan mundur pencairan otomatis escrow mulai di sini.
     */
    public function markDelivered(): void
    {
        $this->transitionTo('delivered', ['delivered_at' => now()], function (): void {
            $this->shipment()->update(['status' => 'delivered', 'delivered_at' => now()]);
            $this->escrowHold()->update(['auto_release_at' => now()->addDays(EscrowHold::AUTO_RELEASE_DAYS)]);
        });
    }

    /**
     * Paket selesai dan dana toko dicairkan ke seller.
     *
     * @param  string  $reason  Nilai `escrow_holds.release_reason`: buyer_confirmed, auto_released, ...
     */
    public function complete(string $reason): void
    {
        $attributes = ['completed_at' => now(), 'delivered_at' => $this->delivered_at ?? now()];

        if ($reason === 'buyer_confirmed') {
            $attributes['buyer_confirmed_at'] = now();
        }

        $this->transitionTo('completed', $attributes, function () use ($reason): void {
            $this->shipment()
                ->where('status', '!=', 'delivered')
                ->update(['status' => 'delivered', 'delivered_at' => now()]);

            $this->escrowHold()->first()?->release($reason);
        });
    }

    /**
     * Membatalkan paket sebelum dikirim. Stok dikembalikan; kalau order sudah
     * dibayar, dana toko ini dikembalikan ke saldo pembeli.
     */
    public function cancel(string $reason, ?User $requestedBy = null): void
    {
        $this->transitionTo('cancelled', ['note' => $reason], function () use ($reason, $requestedBy): void {
            foreach ($this->items as $item) {
                if ($item->product_variant_id !== null) {
                    ProductVariant::whereKey($item->product_variant_id)->increment('stock', $item->quantity);
                }
            }

            $hold = $this->escrowHold()->where('status', 'held')->first();

            if ($hold) {
                $hold->refundToBuyer($requestedBy ?? $this->order->user, $reason);
            }
        });
    }

    /**
     * Mengunci baris paket, menolak transisi yang tidak sah, lalu menyimpan
     * status baru dan menjalankan `$then` dalam satu transaksi. Status order
     * induk diturunkan ulang di akhir.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function transitionTo(string $status, array $attributes = [], ?Closure $then = null): void
    {
        DB::transaction(function () use ($status, $attributes, $then): void {
            $this->status = static::whereKey($this->id)->lockForUpdate()->value('status');

            if (! $this->canTransitionTo($status)) {
                throw new LogicException("Paket {$this->store_order_number} tidak bisa diubah dari {$this->status} ke {$status}.");
            }

            $this->update(['status' => $status, ...$attributes]);

            if ($then) {
                $then();
            }

            $this->order->refresh()->syncStatusFromStores();
        });
    }

    // relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function escrowHold(): HasOne
    {
        return $this->hasOne(EscrowHold::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }
}
