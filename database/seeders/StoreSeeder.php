<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Toko demo, satu per akun seller. Butuh UserSeeder jalan duluan.
     *
     * @var array<string, string>
     */
    private const STORES = [
        'seller@fitmate.test' => 'Archive Supply Co',
        'seller2@fitmate.test' => 'Rimba Denim Works',
        'seller3@fitmate.test' => 'Langkah Footwear',
    ];

    public function run(): void
    {
        foreach (self::STORES as $email => $name) {
            $owner = User::where('email', $email)->firstOrFail();

            Store::updateOrCreate(
                ['user_id' => $owner->id],
                [
                    'name' => $name,
                    'slug' => str($name)->slug()->value(),
                    'description' => $name.' menjual koleksi pilihan di FitMate.',
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'postal_code' => '10110',
                    'status' => 'active',
                    'verified_at' => now(),
                ]
            );
        }
    }
}
