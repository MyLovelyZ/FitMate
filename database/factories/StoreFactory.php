<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory()->seller(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'phone_number' => fake()->numerify('08##########'),
            'email' => fake()->unique()->companyEmail(),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'postal_code' => fake()->numerify('#####'),
            'identity_name' => fake()->name(),
            'identity_number' => fake()->numerify('################'),
            'status' => 'active',
            'submitted_at' => now()->subDays(3),
            'verified_at' => now(),
        ];
    }

    /**
     * Pengajuan baru dari pembeli yang menunggu review admin.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $this->applicantOwner($attributes),
            'status' => 'pending',
            'submitted_at' => now(),
            'verified_at' => null,
        ]);
    }

    public function rejected(string $reason = 'Foto KTP buram dan nama rekening tidak sesuai KTP.'): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $this->applicantOwner($attributes),
            'status' => 'rejected',
            'verified_at' => null,
            'reviewed_at' => now(),
            'review_note' => $reason,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'reviewed_at' => now(),
            'review_note' => 'Banyak komplain barang tidak sesuai deskripsi.',
        ]);
    }

    /**
     * Toko yang dibuat lewat alur pengajuan sekarang: profil toko baru diisi
     * pemilik setelah disetujui admin.
     */
    public function withoutProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'slug' => null,
            'description' => null,
            'phone_number' => null,
            'email' => null,
        ]);
    }

    /**
     * Path foto KTP di disk privat. Filenya sendiri dibuat test lewat Storage::fake.
     */
    public function withIdentityCard(string $path = 'identity-cards/ktp.jpg'): static
    {
        return $this->state(fn (array $attributes) => [
            'identity_card_path' => $path,
        ]);
    }

    /**
     * Pemohon toko belum seller, jadi pemilik bawaan diganti akun pembeli. Pemilik
     * yang diset eksplisit lewat `for($user, 'owner')` atau `create(['user_id' => ...])`
     * tetap dipakai.
     */
    /** @param  array<string, mixed>  $attributes */
    private function applicantOwner(array $attributes): mixed
    {
        return $attributes['user_id'] instanceof Factory ? User::factory() : $attributes['user_id'];
    }
}
