<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    use HasFactory;

    /**
     * Batas waktu bayar setelah checkout. Lewat dari ini scheduler
     * `orders:expire-unpaid` membatalkan order dan mengembalikan stoknya.
     */
    public const PAYMENT_WINDOW_HOURS = 24;

    protected $fillable = [
        'user_id',
        'order_number',
        'recipient_name',
        'recipient_phone',
        'shipping_street',
        'shipping_city',
        'shipping_province',
        'shipping_postal_code',
        'shipping_country',
        'subtotal',
        'shipping_total',
        'discount_total',
        'grand_total',
        'status',
        'note',
        'placed_at',
        'paid_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Status order dari sudut pandang pembeli: kunci tab => [label, nilai `status`].
     * Urutannya juga urutan tab di halaman "Pesanan Saya".
     *
     * @var array<string, array{0: string, 1: array<int, string>}>
     */
    public const BUYER_STATUS_GROUPS = [
        'belum-dibayar' => ['Belum Dibayar', ['pending_payment']],
        'diproses' => ['Diproses', ['paid', 'processing']],
        'dikirim' => ['Dalam Perjalanan', ['shipped']],
        'selesai' => ['Selesai', ['completed']],
        'dibatalkan' => ['Dibatalkan', ['cancelled', 'expired', 'refunded']],
    ];

    /**
     * Langkah progres yang ditampilkan ke pembeli, berurutan. Kelompok
     * `dibatalkan` sengaja tidak ada: pesanan batal keluar dari alur ini.
     *
     * @var array<int, string>
     */
    public const BUYER_STEPS = ['belum-dibayar', 'diproses', 'dikirim', 'selesai'];

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /**
     * Nomor pesanan yang tampil ke pembeli/seller, mis. "ORD-20261021-K7Q2XM".
     */
    public static function newNumber(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    /**
     * Checkout: isi keranjang pembeli jadi satu order dengan satu paket
     * (`store_orders`) per toko, alamat disalin ke kolom `shipping_*`, stok
     * dikurangi, instruksi bayar dibuat, dan keranjang dikosongkan — semuanya
     * dalam satu transaksi. Baris varian dikunci supaya dua pembeli tidak
     * sama-sama mendapatkan barang terakhir.
     *
     * @param  array{recipient_name: string, recipient_phone: string, street: string, city: string, state: string, postal_code: string}  $address
     *
     * @throws ValidationException Kunci `cart`: keranjang kosong atau ada barang yang tidak bisa dibeli.
     */
    public static function placeFromCart(
        User $buyer,
        array $address,
        string $shippingOption,
        string $paymentMethod,
        ?string $vaBank = null,
        ?string $note = null,
    ): self {
        return DB::transaction(function () use ($buyer, $address, $shippingOption, $paymentMethod, $vaBank, $note): self {
            $items = CartItem::query()
                ->whereHas('cart', fn (Builder $cart) => $cart->whereBelongsTo($buyer))
                ->orderBy('id')
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Keranjangmu kosong.']);
            }

            $variants = ProductVariant::with(['product.store', 'size', 'color'])
                ->whereKey($items->pluck('product_variant_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $items->each(fn (CartItem $item) => $item->setRelation('productVariant', $variants->get($item->product_variant_id)));

            $problems = $items->map(fn (CartItem $item): ?string => $item->checkoutProblem($buyer))->filter();

            if ($problems->isNotEmpty()) {
                throw ValidationException::withMessages(['cart' => $problems->values()->all()]);
            }

            $packages = $items->groupBy(fn (CartItem $item): int => $item->productVariant->product->store_id);
            $subtotal = $items->sum(fn (CartItem $item): float => $item->lineTotal());
            $shippingTotal = Shipment::OPTIONS[$shippingOption]['cost'] * $packages->count();

            $order = static::create([
                'user_id' => $buyer->id,
                'order_number' => self::newNumber('ORD'),
                'recipient_name' => $address['recipient_name'],
                'recipient_phone' => $address['recipient_phone'],
                'shipping_street' => $address['street'],
                'shipping_city' => $address['city'],
                'shipping_province' => $address['state'],
                'shipping_postal_code' => $address['postal_code'],
                'shipping_country' => 'Indonesia',
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'grand_total' => $subtotal + $shippingTotal,
                'status' => 'pending_payment',
                'note' => $note ?: null,
                'placed_at' => now(),
                'expires_at' => now()->addHours(self::PAYMENT_WINDOW_HOURS),
            ]);

            foreach ($packages as $package) {
                StoreOrder::openFor($order, $package, $shippingOption);
            }

            Payment::issueFor($order, $paymentMethod, $vaBank);

            CartItem::whereKey($items->modelKeys())->delete();

            return $order;
        });
    }

    /**
     * Membatalkan order yang belum dibayar — oleh pembeli (`cancelled`) atau
     * karena lewat batas bayar (`expired`). Stok semua paket dikembalikan.
     *
     * @return bool false kalau order ternyata sudah tidak menunggu pembayaran.
     */
    public function cancelUnpaid(string $status = 'cancelled'): bool
    {
        return DB::transaction(function () use ($status): bool {
            if (static::whereKey($this->id)->lockForUpdate()->value('status') !== 'pending_payment') {
                return false;
            }

            $reason = $status === 'expired'
                ? 'Batas waktu pembayaran lewat.'
                : 'Dibatalkan pembeli sebelum dibayar.';

            foreach ($this->storeOrders()->with('items')->get() as $storeOrder) {
                if ($storeOrder->canTransitionTo('cancelled')) {
                    $storeOrder->cancel($reason);
                }
            }

            $this->payments()->where('status', 'pending')->update(['status' => $status === 'expired' ? 'expired' : 'failed']);

            $this->update(['status' => $status]);

            return true;
        });
    }

    /**
     * Dipanggil scheduler: batalkan order yang lewat batas bayar.
     *
     * @return int Jumlah order yang dibatalkan.
     */
    public static function expireOverdue(): int
    {
        $expired = 0;

        static::query()
            ->where('status', 'pending_payment')
            ->where('expires_at', '<=', now())
            ->eachById(function (self $order) use (&$expired): void {
                $expired += (int) $order->cancelUnpaid('expired');
            });

        return $expired;
    }

    /**
     * Status order induk diturunkan dari paket-paketnya setelah dibayar:
     * semua selesai → completed, semua sudah dikirim → shipped, ada yang mulai
     * diproses → processing, semua paket batal → refunded (dananya sudah
     * kembali ke saldo pembeli). Order yang belum dibayar tidak disentuh.
     */
    public function syncStatusFromStores(): void
    {
        if (! $this->isPaid()) {
            return;
        }

        $active = $this->storeOrders()
            ->pluck('status')
            ->reject(fn (string $status): bool => in_array($status, ['cancelled', 'refunded'], true));

        $status = match (true) {
            $active->isEmpty() => 'refunded',
            $active->every(fn (string $status): bool => $status === 'completed') => 'completed',
            $active->every(fn (string $status): bool => in_array($status, ['shipped', 'delivered', 'disputed', 'completed'], true)) => 'shipped',
            $active->contains(fn (string $status): bool => $status !== 'pending') => 'processing',
            default => 'paid',
        };

        $this->update(['status' => $status]);
    }

    public function buyerStatusGroup(): string
    {
        foreach (self::BUYER_STATUS_GROUPS as $group => [, $statuses]) {
            if (in_array($this->status, $statuses, true)) {
                return $group;
            }
        }

        return 'diproses';
    }

    public function buyerStatusLabel(): string
    {
        return match ($this->status) {
            'expired' => 'Kedaluwarsa',
            'refunded' => 'Dana Dikembalikan',
            default => self::BUYER_STATUS_GROUPS[$this->buyerStatusGroup()][0],
        };
    }

    /**
     * Posisi pesanan di `BUYER_STEPS`, atau null untuk pesanan yang batal,
     * kedaluwarsa, atau dananya dikembalikan.
     */
    public function buyerStepIndex(): ?int
    {
        $index = array_search($this->buyerStatusGroup(), self::BUYER_STEPS, true);

        return $index === false ? null : $index;
    }

    /**
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function inBuyerStatusGroup(Builder $query, string $group): void
    {
        $query->whereIn('status', self::BUYER_STATUS_GROUPS[$group][1] ?? []);
    }

    // relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function storeOrders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    /**
     * @return HasManyThrough<OrderItem, StoreOrder, $this>
     */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, StoreOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function successfulPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', 'paid');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
