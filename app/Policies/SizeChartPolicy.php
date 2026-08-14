<?php

namespace App\Policies;

use App\Models\SizeChart;
use App\Models\User;

/**
 * BE-033: standar ukuran dikelola terpusat.
 *
 * Ini yang menegakkan keputusan desain "satu standar global" — skema hanya bisa
 * mencegah adanya dua chart yang bersaing, bukan mencegah siapa yang mengubahnya.
 * Berlaku juga untuk SizeChartEntry dan SizeChartEntryMeasurement, yang
 * otorisasinya selalu dicek lewat chart induknya.
 */
class SizeChartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SizeChart $sizeChart): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SizeChart $sizeChart): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, SizeChart $sizeChart): bool
    {
        return $user->isAdmin();
    }
}
