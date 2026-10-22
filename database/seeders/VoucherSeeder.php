<?php

namespace Database\Seeders;

use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    /**
     * Kode promo demo, satu contoh untuk tiap aturan voucher. Idempotent lewat
     * updateOrCreate dengan `code` sebagai kunci alami.
     *
     * @var array<int, array<string, mixed>>
     */
    private const VOUCHERS = [
        [
            'code' => 'FITMATE10',
            'name' => 'Diskon 10% hingga Rp50.000',
            'type' => 'percentage',
            'value' => 10,
            'max_discount' => 50000,
            'min_subtotal' => 100000,
        ],
        [
            'code' => 'HEMAT25K',
            'name' => 'Potongan Rp25.000 min. belanja Rp200.000',
            'type' => 'fixed',
            'value' => 25000,
            'min_subtotal' => 200000,
        ],
        [
            'code' => 'BARU50',
            'name' => 'Pembeli baru: diskon 50% hingga Rp100.000',
            'type' => 'percentage',
            'value' => 50,
            'max_discount' => 100000,
            'per_user_limit' => 1,
        ],
        [
            'code' => 'FLASH100K',
            'name' => 'Flash sale: potongan Rp100.000 untuk 20 pembeli pertama',
            'type' => 'fixed',
            'value' => 100000,
            'min_subtotal' => 500000,
            'usage_limit' => 20,
            'per_user_limit' => 1,
        ],
    ];

    public function run(): void
    {
        foreach (self::VOUCHERS as $voucher) {
            Voucher::updateOrCreate(
                ['code' => $voucher['code']],
                [
                    'max_discount' => null,
                    'min_subtotal' => 0,
                    'usage_limit' => null,
                    'per_user_limit' => null,
                    'starts_at' => null,
                    'expires_at' => null,
                    'is_active' => true,
                    ...$voucher,
                ]
            );
        }
    }
}
