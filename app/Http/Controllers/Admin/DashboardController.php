<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FitFeedback;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dua kartu terakhir adalah yang menutup lingkaran umpan balik ukuran:
     * berapa banyak keluhan ukuran yang masuk (BE-082), dan seberapa sering
     * pembeli menuruti saran FitMate (BE-083).
     */
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'pendingStoreCount' => Store::where('status', StoreStatus::Pending)->count(),
            'pendingProductCount' => Product::where('status', ProductStatus::PendingReview)->count(),
            'todayOrderCount' => Order::whereDate('placed_at', today())->count(),
            'sizeComplaintCount' => $this->sizeComplaintCount(),
            'fitFeedbackBreakdown' => $this->fitFeedbackBreakdown(),
            'recommendationCompliance' => $this->recommendationCompliance(),
        ]);
    }

    private function sizeComplaintCount(): int
    {
        return ProductReview::whereIn('fit_feedback', [
            FitFeedback::TooSmall,
            FitFeedback::TooLarge,
        ])->count();
    }

    /**
     * Sebaran umpan balik ukuran. Kalau satu arah menumpuk, admin punya bukti
     * untuk menyetel ulang rentang di `size_chart_entry_measurements` — bukan
     * menebak-nebak.
     *
     * @return array<string, int>
     */
    private function fitFeedbackBreakdown(): array
    {
        $counts = ProductReview::query()
            ->whereNotNull('fit_feedback')
            ->groupBy('fit_feedback')
            ->pluck(DB::raw('COUNT(*)'), 'fit_feedback');

        return collect(FitFeedback::cases())
            ->mapWithKeys(fn (FitFeedback $feedback): array => [
                $feedback->label() => (int) ($counts[$feedback->value] ?? 0),
            ])
            ->all();
    }

    /**
     * @return array{total: int, followed: int, percentage: float}
     */
    private function recommendationCompliance(): array
    {
        $items = OrderItem::query()
            ->whereNotNull('recommended_size_label')
            ->whereNotNull('size_label')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN recommended_size_label = size_label THEN 1 ELSE 0 END) as followed')
            ->first();

        $total = (int) ($items->total ?? 0);
        $followed = (int) ($items->followed ?? 0);

        return [
            'total' => $total,
            'followed' => $followed,
            'percentage' => $total > 0 ? round($followed / $total * 100, 1) : 0.0,
        ];
    }
}
