<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Pembeli hanya boleh melihat pesanannya sendiri.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->isAdmin();
    }

    public function cancel(User $user, Order $order): bool
    {
        return $order->user_id === $user->id && $order->isCancellable();
    }
}
