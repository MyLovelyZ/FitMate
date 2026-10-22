<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    /**
     * Wallet milik user; dibuat saat pertama kali dibutuhkan (mis. seller baru
     * menerima escrow pertamanya).
     */
    public static function forUser(User $user): self
    {
        return static::firstOrCreate(['user_id' => $user->id], ['currency' => 'IDR']);
    }

    /**
     * Satu-satunya pintu untuk mengubah saldo yang bisa ditarik: kunci baris
     * wallet, catat satu baris buku besar, lalu perbarui cache `balance_available`.
     * `idempotency_key` unique, jadi percobaan kedua dengan kunci yang sama
     * ditolak database dan seluruh transaksinya batal.
     */
    public function post(
        string $type,
        string $direction,
        float $amount,
        Model $reference,
        string $idempotencyKey,
        string $description,
    ): WalletTransaction {
        return DB::transaction(function () use ($type, $direction, $amount, $reference, $idempotencyKey, $description): WalletTransaction {
            $balance = (float) static::whereKey($this->id)->lockForUpdate()->value('balance_available');
            $balanceAfter = $direction === 'credit' ? $balance + $amount : $balance - $amount;

            $transaction = $this->transactions()->create([
                'type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => $reference->getKey(),
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
            ]);

            $this->forceFill(['balance_available' => $balanceAfter])->save();

            return $transaction;
        });
    }

    /**
     * Menaikkan/menurunkan cache `balance_pending`. Tidak ada baris buku besar:
     * sumber kebenarannya adalah `escrow_holds` yang masih `held`.
     */
    public function adjustPending(float $amount): void
    {
        DB::transaction(function () use ($amount): void {
            $pending = (float) static::whereKey($this->id)->lockForUpdate()->value('balance_pending');

            $this->forceFill(['balance_pending' => $pending + $amount])->save();
        });
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
