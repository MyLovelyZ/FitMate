<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan seller yang membuka dashboard sudah punya toko, lalu menyimpannya
 * di request supaya controller tidak perlu mengambilnya berulang-ulang.
 *
 * Toko yang belum diverifikasi tetap boleh masuk — seller perlu bisa menyiapkan
 * produk sambil menunggu admin, hanya saja produknya belum tampil di katalog.
 */
class EnsureUserHasStore
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->user()?->store;

        if (! $store instanceof Store) {
            return redirect()
                ->route('seller.store.edit')
                ->with('warning', 'Lengkapi profil tokomu dulu sebelum mulai berjualan.');
        }

        $request->attributes->set('store', $store);

        return $next($request);
    }
}
