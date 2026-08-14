<?php

namespace App\Http\Controllers;

use App\Enums\FitFeedback;
use App\Enums\StoreOrderStatus;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * BE-080: review hanya boleh ditulis untuk barang yang benar-benar sudah
 * diterima. Satu `order_item` satu review — dijamin unique constraint.
 */
class ProductReviewController extends Controller
{
    public function store(Request $request, OrderItem $orderItem): RedirectResponse
    {
        $orderItem->load('storeOrder.order');

        abort_unless($orderItem->storeOrder->order->user_id === $request->user()->id, 403);

        if (! in_array($orderItem->storeOrder->status, [StoreOrderStatus::Delivered, StoreOrderStatus::Completed], true)) {
            return back()->with('error', 'Ulasan baru bisa ditulis setelah barangnya sampai.');
        }

        if ($orderItem->review()->exists()) {
            return back()->with('error', 'Barang ini sudah pernah kamu ulas.');
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'fit_feedback' => ['nullable', 'string', 'in:'.implode(',', array_column(FitFeedback::cases(), 'value'))],
        ]);

        $orderItem->review()->create([
            ...$validated,
            'product_id' => $orderItem->product_id,
            'user_id' => $request->user()->id,
            'size_purchased' => $orderItem->size_label,
        ]);

        return back()->with('success', 'Terima kasih, ulasanmu membantu pembeli lain memilih ukuran.');
    }
}
