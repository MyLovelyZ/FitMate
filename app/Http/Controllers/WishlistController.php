<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        return view('wishlist.index', [
            'wishlists' => $request->user()
                ->wishlists()
                ->with(['product.store:id,name', 'product.primaryImage'])
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Satu tombol untuk menyimpan dan melepas, karena tampilannya juga satu
     * tombol. Unique `(user_id, product_id)` yang menjaga tidak ada duplikat.
     */
    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $existing = $request->user()->wishlists()->where('product_id', $product->id)->first();

        if ($existing !== null) {
            $existing->delete();

            return back()->with('success', 'Produk dilepas dari wishlist.');
        }

        $request->user()->wishlists()->create(['product_id' => $product->id]);

        return back()->with('success', 'Produk disimpan ke wishlist.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $request->user()->wishlists()->where('product_id', $product->id)->delete();

        return back()->with('success', 'Produk dilepas dari wishlist.');
    }
}
