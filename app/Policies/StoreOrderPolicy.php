<?php

namespace App\Policies;

use App\Models\StoreOrder;
use App\Models\User;

/**
 * Pesanan satu toko tidak boleh terlihat atau tersentuh oleh toko lain (BE-077).
 */
class StoreOrderPolicy
{
    public function view(User $user, StoreOrder $storeOrder): bool
    {
        return $this->owns($user, $storeOrder) || $user->isAdmin();
    }

    public function update(User $user, StoreOrder $storeOrder): bool
    {
        return $this->owns($user, $storeOrder);
    }

    private function owns(User $user, StoreOrder $storeOrder): bool
    {
        return $user->store !== null && $user->store->id === $storeOrder->store_id;
    }
}
