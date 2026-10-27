<?php

namespace App\Models;

use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'percentage' => 'Persentase',
        'fixed' => 'Potongan Rupiah',
    ];

    /**
     * Order dengan status ini tidak dihitung sebagai pemakaian: batal atau
     * kedaluwarsa sebelum dibayar, jadi kuota vouchernya kembali.
     *
     * @var array<int, string>
     */
    public const RELEASED_ORDER_STATUSES = ['cancelled', 'expired'];

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'max_discount',
        'min_subtotal',
        'usage_limit',
        'per_user_limit',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Kode disimpan huruf besar tanpa spasi supaya "fitmate10 " dan "FITMATE10"
     * dianggap kode yang sama.
     */
    public static function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    /**
     * Potongan untuk subtotal barang tertentu, dibulatkan ke bawah ke rupiah
     * penuh dan tidak pernah melebihi subtotalnya.
     */
    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === 'percentage'
            ? $subtotal * (float) $this->value / 100
            : (float) $this->value;

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return floor(min($discount, $subtotal));
    }

    /**
     * Alasan kode ini tidak bisa dipakai pembeli, atau null kalau aman. Dipakai
     * saat kode ditukar di checkout dan dicek ulang di dalam transaksi
     * `Order::placeFromCart()` dengan baris voucher yang sudah dikunci.
     */
    public function redeemProblem(User $buyer, float $subtotal): ?string
    {
        return match (true) {
            ! $this->is_active => 'Kode promo ini sudah tidak berlaku.',
            (bool) $this->starts_at?->isFuture() => 'Kode promo ini baru berlaku mulai '.$this->starts_at->format('d/m/Y').'.',
            (bool) $this->expires_at?->isPast() => 'Kode promo ini sudah kedaluwarsa.',
            $subtotal < (float) $this->min_subtotal => 'Minimal belanja Rp '.number_format((float) $this->min_subtotal, 0, ',', '.').' untuk memakai kode ini.',
            $this->usage_limit !== null && $this->redemptions()->count() >= $this->usage_limit => 'Kuota kode promo ini sudah habis.',
            $this->per_user_limit !== null && $this->redemptions()->whereBelongsTo($buyer)->count() >= $this->per_user_limit => 'Kamu sudah mencapai batas pemakaian kode promo ini.',
            default => null,
        };
    }

    /**
     * Ringkasan nilai potongan, mis. "10% (maks. Rp50.000)" atau "Rp25.000".
     */
    public function benefitLabel(): string
    {
        if ($this->type === 'fixed') {
            return 'Rp'.number_format((float) $this->value, 0, ',', '.');
        }

        $label = rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'%';

        return $this->max_discount === null
            ? $label
            : $label.' (maks. Rp'.number_format((float) $this->max_discount, 0, ',', '.').')';
    }

    protected function code(): Attribute
    {
        return Attribute::set(fn (string $value): string => self::normalizeCode($value));
    }

    /**
     * @param  Builder<Voucher>  $query
     */
    #[Scope]
    protected function withCode(Builder $query, string $code): void
    {
        $query->where('code', self::normalizeCode($code));
    }

    // relationships

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Order yang masih menghabiskan kuota voucher ini.
     */
    public function redemptions(): HasMany
    {
        return $this->orders()->whereNotIn('status', self::RELEASED_ORDER_STATUSES);
    }
}
