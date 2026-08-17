<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserBodyProfile;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Akun demo tiap role, plus beberapa pembeli lengkap dengan alamat & ukuran badan.
     * Semua password: "password".
     */
    public function run(): void
    {
        User::factory()->admin()->create(['name' => 'Demo Admin', 'email' => 'admin@fitmate.test']);
        User::factory()->seller()->create(['name' => 'Demo Seller', 'email' => 'seller@fitmate.test']);
        User::factory()->customerService()->create(['name' => 'Demo CS', 'email' => 'cs@fitmate.test']);

        $buyers = User::factory()
            ->count(10)
            ->create()
            ->push(User::factory()->create([
                'name' => 'Demo Pembeli',
                'email' => 'user@fitmate.test',
            ]));

        foreach ($buyers as $buyer) {
            UserAddress::factory()->for($buyer)->default()->create();
            UserBodyProfile::factory()->for($buyer)->default()->create([
                'gender' => $buyer->gender,
            ]);
        }
    }
}
