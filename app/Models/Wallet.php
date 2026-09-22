<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance_available' => 'decimal:2',
            'balance_pending' => 'decimal:2',
        ];
    }

    /**
     * Saldo sebenarnya menurut buku besar. Kolom `balance_available` cuma cache;
     * pakai ini kalau lagi mengaudit atau mencurigai angkanya melenceng.
     */
    public function ledgerBalance(): string
    {
        $credit = (float) $this->transactions()->where('direction', 'credit')->sum('amount');
        $debit = (float) $this->transactions()->where('direction', 'debit')->sum('amount');

        return number_format($credit - $debit, 2, '.', '');
    }

    public function isBalanced(): bool
    {
        return $this->ledgerBalance() === (string) $this->balance_available;
    }

    // relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function escrowHolds(): HasMany
    {
        return $this->hasMany(EscrowHold::class, 'seller_wallet_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }
}
