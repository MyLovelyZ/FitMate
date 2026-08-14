<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $cart = $request->user()->currentCart()->loadItemsForDisplay();

        return view('cart.index', [
            'cart' => $cart,
            'itemsByStore' => $cart->itemsGroupedByStore(),
            'subtotal' => $cart->subtotal(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $variant = ProductVariant::with('product')->findOrFail($validated['product_variant_id']);

        if (! $variant->is_active || ! $variant->product?->isActive()) {
            return back()->with('error', 'Varian ini sedang tidak dijual.');
        }

        $cart = $request->user()->currentCart();
        $item = $cart->items()->firstOrNew(['product_variant_id' => $variant->id]);
        $quantity = $item->quantity + $validated['quantity'];

        if ($quantity > $variant->stock) {
            return back()->with('error', "Stok tersisa {$variant->stock}, tidak cukup untuk jumlah yang kamu minta.");
        }

        $item->fill([
            'quantity' => $quantity,
            'note' => $validated['note'] ?? $item->note,
        ])->save();

        return back()->with('success', 'Barang masuk keranjang.');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->authorizeItem($request, $cartItem);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cartItem->loadMissing('variant');

        if ($validated['quantity'] > $cartItem->variant->stock) {
            return back()->with('error', "Stok tersisa {$cartItem->variant->stock}.");
        }

        $cartItem->update($validated);

        return back()->with('success', 'Jumlah diperbarui.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->authorizeItem($request, $cartItem);

        $cartItem->delete();

        return back()->with('success', 'Barang dihapus dari keranjang.');
    }

    /**
     * Item keranjang tidak punya policy sendiri — cukup pastikan pemiliknya
     * memang yang sedang masuk, supaya keranjang orang lain tidak bisa disentuh
     * lewat tebakan id.
     */
    private function authorizeItem(Request $request, CartItem $cartItem): void
    {
        abort_unless($cartItem->cart->user_id === $request->user()->id, 403);
    }
}
