<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Gender;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SizeChart;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        return view('seller.products.index', [
            'products' => $store->products()
                ->with(['category:id,name', 'variants'])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'statusOptions' => ProductStatus::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('seller.products.form', [
            'product' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $product = DB::transaction(function () use ($request, $store): Product {
            $product = $store->products()->create($this->productAttributes($request));

            $this->syncVariants($product, $request->validated('variants'));
            $this->storeImages($request, $product);

            return $product;
        });

        return redirect()
            ->route('seller.products.edit', $product)
            ->with('success', 'Produk tersimpan.');
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('seller.products.form', [
            'product' => $product->load(['variants.sizeChartEntry', 'images']),
            ...$this->formOptions(),
        ]);
    }

    public function update(StoreProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $product->update($this->productAttributes($request));

            $this->syncVariants($product, $request->validated('variants'));
            $this->storeImages($request, $product);
        });

        return back()->with('success', 'Produk diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()
            ->route('seller.products.index')
            ->with('success', 'Produk dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function productAttributes(StoreProductRequest $request): array
    {
        $validated = $request->safe()->except(['variants', 'images']);
        $status = ProductStatus::from($validated['status']);

        return [
            ...$validated,
            'slug' => $validated['slug'] ?? Str::slug($validated['name']).'-'.Str::lower(Str::random(5)),
            // Produk baru tayang saat statusnya benar-benar aktif; menyimpan
            // sebagai draf tidak boleh diam-diam menerbitkannya.
            'published_at' => $status === ProductStatus::Active ? now() : null,
        ];
    }

    /**
     * Varian yang tidak ikut dikirim dianggap dihapus, supaya form varian yang
     * dinamis di sisi klien tetap jadi sumber kebenaran tunggal.
     *
     * @param  list<array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $keptIds = [];

        foreach ($variants as $variant) {
            $attributes = [
                'size_chart_entry_id' => $variant['size_chart_entry_id'] ?? null,
                'sku' => $variant['sku'],
                'color_name' => $variant['color_name'] ?? null,
                'color_hex' => $variant['color_hex'] ?? null,
                'price' => $variant['price'],
                'compare_at_price' => $variant['compare_at_price'] ?? null,
                'stock' => $variant['stock'],
                'is_active' => (bool) ($variant['is_active'] ?? true),
            ];

            $record = isset($variant['id'])
                ? tap($product->variants()->findOrFail($variant['id']))->update($attributes)
                : $product->variants()->create($attributes);

            $keptIds[] = $record->id;
        }

        $product->variants()->whereNotIn('id', $keptIds)->delete();
    }

    private function storeImages(StoreProductRequest $request, Product $product): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $sortOrder = (int) $product->images()->max('sort_order');

        foreach ($request->file('images') as $image) {
            $product->images()->create([
                'path' => $image->store('products', 'public'),
                'alt_text' => $product->name,
                'sort_order' => ++$sortOrder,
                'is_primary' => ! $hasPrimary && $sortOrder === 1,
            ]);

            $hasPrimary = true;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->active()->with('parent')->orderBy('name')->get(),
            'brands' => Brand::query()->active()->orderBy('name')->pluck('name', 'id'),
            'sizeCharts' => SizeChart::query()->active()->with('entries')->get(),
            'genderOptions' => Gender::options(),
            'statusOptions' => collect(ProductStatus::options())
                ->only([ProductStatus::Draft->value, ProductStatus::PendingReview->value, ProductStatus::Active->value, ProductStatus::Inactive->value])
                ->all(),
        ];
    }
}
