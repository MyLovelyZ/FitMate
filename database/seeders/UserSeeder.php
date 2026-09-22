<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserBodyProfile;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Akun demo tiap role, plus beberapa pembeli lengkap dengan alamat & ukuran
     * badan. Tiga seller supaya katalognya benar-benar terlihat sebagai marketplace.
     *
     * Semua password: "password".
     *
     * Akun tetap dicari dulu lewat email, jadi seeder ini aman dijalankan ulang.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const STAFF = [
        ['Demo Admin', 'admin@fitmate.test', 'admin'],
        ['Demo CS', 'cs@fitmate.test', 'customerservice'],
        ['Demo Seller', 'seller@fitmate.test', 'seller'],
        ['Demo Seller Dua', 'seller2@fitmate.test', 'seller'],
        ['Demo Seller Tiga', 'seller3@fitmate.test', 'seller'],
    ];

    private const BUYER_COUNT = 11;

    public function run(): void
    {
        foreach (self::STAFF as [$name, $email, $role]) {
            $this->firstOrCreateUser($name, $email, $role);
        }

        $this->firstOrCreateUser('Demo Pembeli', 'user@fitmate.test', 'user');

        $missingBuyers = self::BUYER_COUNT - User::where('role', 'user')->count();

        if ($missingBuyers > 0) {
            User::factory()->count($missingBuyers)->create();
        }

        User::where('role', 'user')
            ->whereDoesntHave('addresses')
            ->each(function (User $buyer): void {
                UserAddress::factory()->for($buyer)->default()->create();
                UserBodyProfile::factory()->for($buyer)->default()->create([
                    'gender' => $buyer->gender,
                ]);
            });
    }

    private function firstOrCreateUser(string $name, string $email, string $role): User
    {
        return User::where('email', $email)->first()
            ?? User::factory()->create([
                'name' => $name,
                'email' => $email,
                'role' => $role,
            ]);
    }
}
