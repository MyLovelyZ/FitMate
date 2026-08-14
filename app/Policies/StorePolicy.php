<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    public function update(User $user, Store $store): bool
    {
        return $store->user_id === $user->id || $user->isAdmin();
    }

    /**
     * Verifikasi toko adalah wewenang admin (KEP-5: seller mendaftar sendiri,
     * lalu diverifikasi admin).
     */
    public function verify(User $user, Store $store): bool
    {
        return $user->isAdmin();
    }
}
