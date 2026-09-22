<?php

namespace Database\Seeders;

use App\Models\PayoutAccount;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    /**
     * Dompet untuk semua user — seller buat menerima hasil penjualan, pembeli buat
     * menampung refund — plus rekening pencairan untuk tiap seller.
     *
     * Saldo sengaja dibiarkan nol. Yang mengisinya adalah MarketplaceOrderSeeder
     * lewat mutasi buku besar, bukan diset langsung ke kolom saldo.
     */
    public function run(): void
    {
        User::query()->eachById(function (User $user): void {
            Wallet::firstOrCreate(['user_id' => $user->id], ['currency' => 'IDR']);

            if ($user->role !== 'seller') {
                return;
            }

            PayoutAccount::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => 'bank',
                    'bank_code' => 'bca',
                    'account_number' => '817'.str_pad((string) $user->id, 7, '0', STR_PAD_LEFT),
                ],
                [
                    'account_name' => $user->name,
                    'is_default' => true,
                    'verified_at' => now(),
                ]
            );
        });
    }
}
