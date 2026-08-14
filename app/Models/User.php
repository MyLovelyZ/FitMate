<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'gender' => Gender::class,
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    // Bagian Relationships

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

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    // Bagian Helper

    /**
     * Profil badan yang dipakai kalau user tidak memilih secara eksplisit.
     */
    public function activeBodyProfile(): ?UserBodyProfile
    {
        return $this->bodyProfiles()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * Keranjang milik user, dibuat kalau belum ada.
     */
    public function currentCart(): Cart
    {
        return $this->cart()->firstOrCreate([]);
    }

    public function isSeller(): bool
    {
        return $this->role === UserRole::Seller;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Superadmin], true);
    }
}
