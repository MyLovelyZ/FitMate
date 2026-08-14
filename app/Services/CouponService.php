<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Validasi dan pencatatan pemakaian kupon.
 */
class CouponService
{
    /**
     * Ambil kupon yang benar-benar boleh dipakai user ini untuk subtotal segitu.
     *
     * @throws ValidationException kalau kuponnya tidak memenuhi salah satu syarat
     */
    public function resolve(string $code, User $user, float $subtotal): Coupon
    {
        $coupon = Coupon::query()->valid()->where('code', $code)->first();

        if (! $coupon instanceof Coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Kode kupon tidak ditemukan, sudah kedaluwarsa, atau kuotanya habis.',
            ]);
        }

        if (! $coupon->meetsMinimumPurchase($subtotal)) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Belanjaan minimal Rp'.number_format((float) $coupon->min_purchase, 0, ',', '.').' untuk memakai kupon ini.',
            ]);
        }

        if ($coupon->usedBy($user)) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Kamu sudah pernah memakai kupon ini.',
            ]);
        }

        return $coupon;
    }

    /**
     * Kupon yang gagal divalidasi cukup diabaikan, dipakai saat halaman checkout
     * hanya ingin menampilkan perkiraan potongan.
     */
    public function tryResolve(?string $code, User $user, float $subtotal): ?Coupon
    {
        if ($code === null || $code === '') {
            return null;
        }

        try {
            return $this->resolve($code, $user, $subtotal);
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * Catat pemakaian kupon dan naikkan counter-nya.
     */
    public function recordUsage(Coupon $coupon, Order $order, float $discount): void
    {
        $coupon->usages()->create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'discount_amount' => $discount,
            'used_at' => now(),
        ]);

        $coupon->increment('used_count');
    }
}
