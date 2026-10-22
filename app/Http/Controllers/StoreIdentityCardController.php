<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto KTP pemohon toko disimpan di disk privat, jadi tidak punya URL publik.
 * Route ini (di grup middleware `admin`) yang mengalirkannya ke halaman review.
 */
class StoreIdentityCardController extends Controller
{
    public function __invoke(Store $store): StreamedResponse
    {
        abort_unless(
            $store->identity_card_path && Storage::disk(Store::IDENTITY_DISK)->exists($store->identity_card_path),
            404
        );

        return Storage::disk(Store::IDENTITY_DISK)->response($store->identity_card_path, headers: [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
