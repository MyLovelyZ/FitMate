<?php

namespace App\Policies;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;

/**
 * Seller hanya boleh menyentuh produk tokonya sendiri (BE-090).
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSeller() || $user->isAdmin();
    }

    public function view(User $user, Product $product): bool
    {
        return $this->owns($user, $product) || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSeller();
    }

    public function update(User $user, Product $product): bool
    {
        return $this->owns($user, $product) || $user->isAdmin();
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->owns($user, $product) || $user->isAdmin();
    }

    /**
     * Persetujuan produk (`pending_review` → `active`/`rejected`) adalah wewenang
     * admin, bukan seller — kalau tidak, alur reviewnya tidak ada artinya.
     */
    public function review(User $user, Product $product): bool
    {
        return $user->isAdmin() && $product->status === ProductStatus::PendingReview;
    }

    private function owns(User $user, Product $product): bool
    {
        return $user->store !== null && $user->store->id === $product->store_id;
    }
}
