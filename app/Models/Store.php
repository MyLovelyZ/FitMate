<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

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
        'status',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

    // relationships

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
