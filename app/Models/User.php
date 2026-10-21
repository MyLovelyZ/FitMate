<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

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
            return ['Dashboard Toko' => 'seller.dashboard'] + $shopping;
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
}
