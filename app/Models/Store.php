<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Label status untuk admin dan pemilik toko; kuncinya sama dengan enum `stores.status`.
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'pending' => 'Menunggu Review',
        'active' => 'Aktif',
        'rejected' => 'Ditolak',
        'suspended' => 'Ditangguhkan',
    ];

    /**
     * Foto KTP tidak boleh bisa diakses publik, jadi disimpan di disk privat.
     */
    public const IDENTITY_DISK = 'local';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'logo',
        'banner',
        'phone_number',
        'email',
        'street',
        'city',
        'province',
        'postal_code',
        'identity_name',
        'identity_number',
        'identity_card_path',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'verified_at',
    ];

    /**
     * NIK dan lokasi foto KTP tidak ikut terserialisasi ke JSON/array.
     *
     * @var list<string>
     */
    protected $hidden = [
        'identity_number',
        'identity_card_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'identity_number' => 'encrypted',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'verified_at' => 'datetime',
            'rating_average' => 'decimal:2',
            'rating_count' => 'integer',
        ];
    }

    /**
     * Toko baru boleh berjualan setelah diverifikasi. Sebagai pihak ketiga yang
     * menahan uang pembeli, kita ngk boleh menerima order dari toko yang belum jelas.
     */
    public function canSell(): bool
    {
        return $this->status === 'active' && $this->verified_at !== null;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Slug unik dari nama toko; ditambah angka kalau sudah dipakai toko lain.
     */
    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'toko';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Admin menyetujui pengajuan: toko aktif, pemiliknya jadi seller, dan rekening
     * pencairan default ikut ditandai terverifikasi karena sudah dicek admin saat
     * review (pencairan hanya boleh ke rekening terverifikasi). Pemilik tetap bisa
     * belanja (lihat `User::canShop()`).
     */
    public function approve(User $admin): void
    {
        DB::transaction(function () use ($admin): void {
            $this->update([
                'status' => 'active',
                'verified_at' => $this->verified_at ?? now(),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => null,
            ]);

            if ($this->owner->role === 'user') {
                $this->owner->update(['role' => 'seller']);
            }

            $this->owner->payoutAccounts()
                ->where('is_default', true)
                ->whereNull('verified_at')
                ->update(['verified_at' => now()]);
        });
    }

    /**
     * Rekening pencairan default milik pemilik toko.
     */
    public function payoutAccount(): ?PayoutAccount
    {
        return $this->owner?->payoutAccounts()->where('is_default', true)->first();
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? asset('storage/'.$this->logo) : null;
    }

    public function reject(User $admin, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => $reason,
        ]);
    }

    /**
     * Toko aktif yang melanggar aturan: produknya hilang dari katalog, tapi
     * riwayat pesanan dan akun seller-nya tetap ada.
     */
    public function suspend(User $admin, string $reason): void
    {
        $this->update([
            'status' => 'suspended',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => $reason,
        ]);
    }

    public function reactivate(User $admin): void
    {
        $this->update([
            'status' => 'active',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => null,
        ]);
    }

    /**
     * Toko yang tampil ke publik dan boleh menerima pesanan.
     *
     * @param  Builder<Store>  $query
     */
    #[Scope]
    protected function selling(Builder $query): void
    {
        $query->where('status', 'active')->whereNotNull('verified_at');
    }

    // relationships

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function storeOrders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }
}
