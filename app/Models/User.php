<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'profile_picture',
        'gender',
        'role',
        'theme',
        'banned_at',
        'banned_by',
        'ban_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'banned_at' => 'datetime',
        ];
    }

    /**
     * Nama tampilan tiap role; kuncinya sama dengan enum `users.role`.
     *
     * @var array<string, string>
     */
    public const ROLE_LABELS = [
        'user' => 'Pembeli',
        'seller' => 'Seller',
        'customerservice' => 'Customer Service',
        'admin' => 'Admin',
        'superadmin' => 'Super Admin',
    ];

    /**
     * Pelanggan FitMate: yang diatur admin (blokir, hapus, lihat barangnya).
     *
     * @var array<int, string>
     */
    public const CUSTOMER_ROLES = ['user', 'seller'];

    /**
     * Role yang bisa diberikan superadmin dari halaman pengguna. `seller` mengikuti
     * persetujuan toko dan `superadmin` hanya dibuat lewat seeder, jadi tidak ada di sini.
     *
     * @var array<int, string>
     */
    public const ASSIGNABLE_ROLES = ['user', 'customerservice', 'admin'];

    /**
     * Pilihan tema di profil; kuncinya sama dengan enum `users.theme`.
     *
     * @var array<string, string>
     */
    public const THEMES = [
        'light' => 'Terang',
        'dark' => 'Gelap',
        'system' => 'Ikuti Perangkat',
    ];

    /**
     * Nilai `<html data-theme>`. Tamu dan akun lama tanpa pilihan mengikuti perangkat.
     */
    public static function themeFor(?self $user): string
    {
        return $user?->theme ?? 'system';
    }

    /**
     * Nama route halaman utama untuk role ini. Dipakai sebagai tujuan setelah
     * login dan tautan "Akun" di navbar.
     */
    public function dashboardRouteName(): string
    {
        return match ($this->role) {
            'superadmin' => 'superadmin.dashboard',
            'admin' => 'admin.dashboard',
            'seller' => 'seller.dashboard',
            'customerservice' => 'cs.dashboard',
            default => 'profile',
        };
    }

    /**
     * Pembeli dan seller boleh belanja (keranjang, pesanan, ukuran badan, alamat).
     * Staf platform (CS, admin, superadmin) tidak.
     */
    public function canShop(): bool
    {
        return in_array($this->role, ['user', 'seller'], true);
    }

    /**
     * Pembeli yang belum punya toko aktif boleh mengajukan/memperbaiki pengajuan toko.
     */
    public function canApplyForStore(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Menu akun di navbar dan halaman profil: label => nama route.
     *
     * @return array<string, string>
     */
    public function accountLinks(): array
    {
        $shopping = [
            'Profil Saya' => 'profile',
            'Pesanan' => 'user.orders',
            'Keranjang' => 'user.cart',
        ];

        if ($this->role === 'seller') {
            $profile = $this->store?->needsProfile() ? ['Lengkapi Profil Toko' => 'stores.create'] : [];

            return ['Dashboard Toko' => 'seller.dashboard'] + $profile + $shopping;
        }

        if ($this->role === 'user') {
            $storeStatus = $this->store?->status;

            return $shopping + [
                ($storeStatus === null ? 'Buka Toko' : 'Status Pengajuan Toko') => 'stores.create',
            ];
        }

        return [
            'Dashboard' => $this->dashboardRouteName(),
            'Profil Saya' => 'profile',
        ];
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * Siapa boleh memblokir dan menghapus siapa. Admin hanya mengatur pelanggan
     * dan seller. Superadmin semua akun kecuali sesama superadmin (termasuk
     * dirinya sendiri), supaya pengelola tertinggi tidak bisa saling mengunci.
     */
    public function canModerate(self $target): bool
    {
        return match ($this->role) {
            'superadmin' => $target->role !== 'superadmin',
            'admin' => in_array($target->role, self::CUSTOMER_ROLES, true),
            default => false,
        };
    }

    /**
     * Admin hanya bisa membuka akun pelanggan dan seller; superadmin semua akun.
     */
    public function canView(self $target): bool
    {
        return $this->role === 'superadmin' || $this->canModerate($target);
    }

    /**
     * Alasan role akun ini tidak bisa diubah superadmin, atau null kalau bisa.
     */
    public function roleChangeBlocker(): ?string
    {
        return match (true) {
            $this->role === 'superadmin' => 'Role superadmin tidak bisa diubah.',
            Store::withTrashed()->whereBelongsTo($this, 'owner')->exists() => 'Akun ini punya toko, jadi rolenya mengikuti status tokonya.',
            default => null,
        };
    }

    /**
     * Superadmin menaikkan pelanggan jadi admin/CS atau mengembalikannya.
     *
     * @throws LogicException Kalau role tidak boleh diberikan atau akun ini tidak bisa diubah.
     */
    public function changeRole(string $role): void
    {
        if (! in_array($role, self::ASSIGNABLE_ROLES, true)) {
            throw new LogicException("Role {$role} tidak bisa diberikan dari halaman pengguna.");
        }

        if ($blocker = $this->roleChangeBlocker()) {
            throw new LogicException($blocker);
        }

        $this->update(['role' => $role]);
    }

    /**
     * Tab area admin. Superadmin memakai area yang sama, ditambah Pengaturan.
     *
     * @return array<string, string> Nama route => label.
     */
    public function adminAreaLinks(): array
    {
        $links = [
            'admin.dashboard' => 'Dashboard',
            'admin.stores.index' => 'Toko',
            'admin.users.index' => 'Pengguna',
            'admin.products.index' => 'Produk',
            'admin.vouchers.index' => 'Kode Promo',
        ];

        return $this->role === 'superadmin' ? $links + ['superadmin.settings' => 'Pengaturan'] : $links;
    }

    /**
     * Memblokir akun: tidak bisa masuk lagi, dan sesi yang masih terbuka
     * dikeluarkan middleware `EnsureUserIsNotBanned`. Toko miliknya yang sedang
     * aktif ikut ditangguhkan supaya produknya hilang dari katalog. Membuka
     * blokir tidak mengaktifkan tokonya lagi; itu keputusan terpisah di halaman
     * review toko.
     */
    public function ban(self $by, string $reason): void
    {
        DB::transaction(function () use ($by, $reason): void {
            $this->update([
                'banned_at' => now(),
                'banned_by' => $by->id,
                'ban_reason' => $reason,
            ]);

            if ($this->store?->status === 'active') {
                $this->store->suspend($by, 'Pemilik toko diblokir: '.$reason);
            }
        });
    }

    public function unban(): void
    {
        $this->update([
            'banned_at' => null,
            'banned_by' => null,
            'ban_reason' => null,
        ]);
    }

    /**
     * Alasan akun ini tidak boleh dihapus permanen, atau null kalau aman.
     * Riwayat uang (pesanan, buku besar, penarikan, refund, sengketa) tidak boleh
     * ikut hilang; akun seperti itu cukup diblokir.
     */
    public function deletionBlocker(): ?string
    {
        return match (true) {
            $this->orders()->exists() => 'Akun ini punya riwayat pesanan sebagai pembeli.',
            StoreOrder::whereHas('store', fn ($store) => $store->withTrashed()->whereBelongsTo($this, 'owner'))->exists() => 'Toko milik akun ini punya riwayat pesanan.',
            (bool) $this->wallet?->transactions()->exists() => 'Akun ini punya riwayat saldo di dompet FitMate.',
            Payout::whereBelongsTo($this)->exists() => 'Akun ini punya riwayat penarikan dana.',
            Refund::where('requested_by', $this->id)->exists() || Dispute::where('opened_by', $this->id)->exists() => 'Akun ini tercatat di riwayat refund atau komplain.',
            default => null,
        };
    }

    /**
     * Menghapus akun permanen beserta alamat, keranjang, toko, dan produknya
     * (foreign key cascade). File KTP, logo toko, dan foto profil ikut dibuang.
     *
     * @throws LogicException Kalau akun masih punya riwayat transaksi.
     */
    public function deleteAccount(): void
    {
        if ($blocker = $this->deletionBlocker()) {
            throw new LogicException($blocker);
        }

        $store = Store::withTrashed()->whereBelongsTo($this, 'owner')->first();

        DB::transaction(fn () => $this->delete());

        if ($store?->identity_card_path) {
            Storage::disk(Store::IDENTITY_DISK)->delete($store->identity_card_path);
        }

        if ($store?->logo) {
            Storage::disk('public')->delete($store->logo);
        }

        if ($this->profile_picture) {
            Storage::disk('public')->delete($this->profile_picture);
        }
    }

    /**
     * Jumlah barang di keranjang (bukan jumlah baris), untuk lencana di navbar.
     */
    public function cartQuantity(): int
    {
        return (int) CartItem::query()
            ->whereHas('cart', fn ($query) => $query->whereBelongsTo($this))
            ->sum('quantity');
    }

    public function profilePictureUrl(): ?string
    {
        return $this->profile_picture
            ? asset('storage/'.$this->profile_picture)
            : null;
    }

    /**
     * Maksimal dua huruf dari kata pertama dan terakhir nama, mis. "Demo Seller" → "DS".
     */
    public function initials(): string
    {
        $words = Str::of($this->name)->squish()->explode(' ')->filter();

        return Str::upper(
            Str::substr((string) $words->first(), 0, 1).($words->count() > 1 ? Str::substr((string) $words->last(), 0, 1) : '')
        );
    }

    // Relationships

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(UserAddress::class)->where('is_default', true);
    }

    public function bodyProfiles(): HasMany
    {
        return $this->hasMany(UserBodyProfile::class);
    }

    public function defaultBodyProfile(): HasOne
    {
        return $this->hasOne(UserBodyProfile::class)->where('is_default', true);
    }

    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function payoutAccounts(): HasMany
    {
        return $this->hasMany(PayoutAccount::class);
    }

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'banned_by');
    }
}
