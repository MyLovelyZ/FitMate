<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BE-050 & BE-056: verifikasi toko dan persetujuan produk.
 *
 * Alur produk: `draft` → `pending_review` → `active` / `rejected`.
 * Alur toko: `pending` → `active` / `rejected`, plus `suspended`.
 */
class ModerationController extends Controller
{
    public function index(): View
    {
        return view('admin.moderation.index', [
            'pendingStores' => Store::query()
                ->where('status', StoreStatus::Pending)
                ->with('owner:id,name,email')
                ->latest()
                ->get(),
            'pendingProducts' => Product::query()
                ->where('status', ProductStatus::PendingReview)
                ->with(['store:id,name', 'category:id,name,size_type', 'sizeChart:id,name,size_type', 'variants'])
                ->latest()
                ->get(),
        ]);
    }

    public function verifyStore(Request $request, Store $store): RedirectResponse
    {
        $this->authorize('verify', $store);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,rejected,suspended'],
        ]);

        $status = StoreStatus::from($validated['status']);

        $store->update([
            'status' => $status,
            'verified_at' => $status === StoreStatus::Active ? now() : null,
        ]);

        // Toko yang ditolak atau ditangguhkan tidak boleh meninggalkan produknya
        // tetap tayang di katalog.
        if ($status !== StoreStatus::Active) {
            $store->products()->where('status', ProductStatus::Active)->update([
                'status' => ProductStatus::Inactive,
                'published_at' => null,
            ]);
        }

        return back()->with('success', "Status toko {$store->name} diubah menjadi {$status->label()}.");
    }

    public function reviewProduct(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('review', $product);

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
        ]);

        $approved = $validated['decision'] === 'approve';

        $product->update([
            'status' => $approved ? ProductStatus::Active : ProductStatus::Rejected,
            'published_at' => $approved ? now() : null,
        ]);

        return back()->with('success', $approved ? 'Produk disetujui dan sudah tayang.' : 'Produk ditolak.');
    }
}
