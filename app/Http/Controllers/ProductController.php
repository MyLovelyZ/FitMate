<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\SizeRecommendationResult;
use App\Services\SizeRecommendationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private SizeRecommendationService $recommendations) {}

    /**
     * Katalog publik: daftar, cari, filter, urutkan.
     */
    public function index(Request $request): View
    {
        return view('products.index', [
            'products' => $this->query($request)->paginate((int) config('fitmate.catalog.per_page'))->withQueryString(),
            'categories' => Category::query()->active()->orderBy('name')->pluck('name', 'slug'),
            'brands' => Brand::query()->active()->orderBy('name')->pluck('name', 'slug'),
            'genderOptions' => Gender::options(),
            'sortOptions' => $this->sortOptions(),
        ]);
    }

    /**
     * Detail produk — layar utama FitMate. Selain data produk, halaman ini
     * menampilkan ukuran yang disarankan beserta alasannya per dimensi.
     */
    public function show(Request $request, Product $product): View
    {
        abort_unless($product->isActive() || $request->user()?->can('view', $product), 404);

        $product->load([
            'store',
            'category',
            'brand',
            'images',
            'sizeChart.entries.measurements.bodyMeasurement',
            'variants.sizeChartEntry',
            'reviews.user:id,name',
        ]);

        $this->countView($product);

        return view('products.show', [
            'product' => $product,
            'recommendation' => $this->recommendationFor($request, $product),
            'sizeChart' => $product->sizeChart,
            'relatedProducts' => Product::query()
                ->published()
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->getKey())
                ->with(['store:id,name', 'primaryImage'])
                ->take(4)
                ->get(),
        ]);
    }

    private function recommendationFor(Request $request, Product $product): ?SizeRecommendationResult
    {
        $profile = $request->user()?->activeBodyProfile();

        if ($profile === null) {
            return null;
        }

        return $this->recommendations->forProduct($profile, $product);
    }

    /**
     * @return Builder<Product>
     */
    private function query(Request $request): Builder
    {
        return Product::query()
            ->published()
            ->with(['store:id,name', 'brand:id,name', 'primaryImage'])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $keyword = '%'.$request->string('q')->trim().'%';

                $query->where(function (Builder $inner) use ($keyword): void {
                    $inner->where('name', 'like', $keyword)
                        ->orWhere('description', 'like', $keyword);
                });
            })
            ->when($request->filled('category'), fn (Builder $query) => $query->whereHas(
                'category',
                fn (Builder $inner) => $inner->where('slug', $request->string('category')),
            ))
            ->when($request->filled('brand'), fn (Builder $query) => $query->whereHas(
                'brand',
                fn (Builder $inner) => $inner->where('slug', $request->string('brand')),
            ))
            ->when($request->filled('gender'), fn (Builder $query) => $query->where('target_gender', $request->string('gender')))
            ->when($request->filled('min_price'), fn (Builder $query) => $query->where('base_price', '>=', $request->float('min_price')))
            ->when($request->filled('max_price'), fn (Builder $query) => $query->where('base_price', '<=', $request->float('max_price')))
            ->when($request->filled('min_rating'), fn (Builder $query) => $query->where('rating_average', '>=', $request->float('min_rating')))
            ->when($request->filled('size'), fn (Builder $query) => $query->whereHas(
                'variants.sizeChartEntry',
                fn (Builder $inner) => $inner->where('label', $request->string('size')),
            ))
            ->when($request->filled('color'), fn (Builder $query) => $query->whereHas(
                'variants',
                fn (Builder $inner) => $inner->where('color_name', $request->string('color')),
            ))
            ->tap(fn (Builder $query) => $this->applySort($query, $request->string('sort')->toString()));
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('base_price'),
            'price_desc' => $query->orderByDesc('base_price'),
            'rating' => $query->orderByDesc('rating_average'),
            'popular' => $query->orderByDesc('sold_count'),
            default => $query->latest('published_at'),
        };
    }

    /**
     * @return array<string, string>
     */
    private function sortOptions(): array
    {
        return [
            'latest' => 'Terbaru',
            'price_asc' => 'Harga Terendah',
            'price_desc' => 'Harga Tertinggi',
            'rating' => 'Rating Tertinggi',
            'popular' => 'Terlaris',
        ];
    }

    /**
     * BE-063: counter view tidak boleh memicu `update()` model penuh tiap
     * request. Increment lewat query builder biasa jauh lebih murah dan tidak
     * menyentuh `updated_at`, jadi urutan "terbaru" tidak ikut kacau.
     */
    private function countView(Product $product): void
    {
        DB::table('products')->where('id', $product->getKey())->increment('view_count');
    }
}
