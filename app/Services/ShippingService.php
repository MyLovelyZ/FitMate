<?php

namespace App\Services;

use App\Models\CartItem;
use Illuminate\Support\Collection;

/**
 * Perhitungan ongkos kirim per toko.
 *
 * KEP-4 belum diputuskan, jadi untuk sekarang tarifnya flat dan hanya
 * memperhitungkan berat. Kalau nanti pakai RajaOngkir atau Biteship, ganti isi
 * kelas ini saja — CheckoutService memanggilnya lewat antarmuka yang sama.
 */
class ShippingService
{
    /**
     * Ongkir untuk satu kelompok item milik satu toko.
     *
     * @param  Collection<int, CartItem>  $items
     */
    public function costFor(Collection $items): float
    {
        $baseRate = (float) config('fitmate.shipping.flat_rate');
        $baseWeight = (int) config('fitmate.shipping.base_weight_gram');
        $perExtraKg = (float) config('fitmate.shipping.per_extra_kg');

        $weight = $this->totalWeight($items);
        $extraKg = max(0, (int) ceil(($weight - $baseWeight) / 1000));

        return $baseRate + ($extraKg * $perExtraKg);
    }

    /**
     * @param  Collection<int, CartItem>  $items
     */
    public function totalWeight(Collection $items): int
    {
        return (int) $items->sum(
            fn (CartItem $item): int => $item->variant->shippingWeight() * $item->quantity,
        );
    }

    /**
     * @return array<string, string>
     */
    public function couriers(): array
    {
        return config('fitmate.shipping.couriers');
    }
}
