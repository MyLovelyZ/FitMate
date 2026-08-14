<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryType;
use App\Enums\SizeType;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * BE-057: CRUD kategori bertingkat dan brand.
 *
 * `size_type` kategori menentukan apakah produk di bawahnya ikut perhitungan
 * ukuran — mengubahnya berdampak ke seluruh produk di kategori itu, jadi
 * perubahan ditolak selama masih ada produk yang memakainya.
 */
class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()->with('children')->roots()->get(),
            'brands' => Brand::query()->orderBy('name')->get(),
            'typeOptions' => CategoryType::options(),
            'sizeTypeOptions' => SizeType::options(),
            'parentOptions' => Category::query()->roots()->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        Category::create([...$validated, 'slug' => Str::slug($validated['name'])]);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $this->validated($request, $category);

        if ($validated['size_type'] !== $category->size_type->value && $category->products()->exists()) {
            return back()->with(
                'error',
                'Kategori ini sudah dipakai produk. Mengubah jenis ukurannya akan membuat tabel ukuran produk-produk itu tidak cocok lagi.',
            );
        }

        $category->update($validated);

        return back()->with('success', 'Kategori diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return back()->with('error', 'Kategori masih dipakai produk atau punya sub-kategori.');
        }

        $category->delete();

        return back()->with('success', 'Kategori dihapus.');
    }

    public function storeBrand(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:brands,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        Brand::create([...$validated, 'slug' => Str::slug($validated['name']), 'is_active' => true]);

        return back()->with('success', 'Brand ditambahkan.');
    }

    public function destroyBrand(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return back()->with('error', 'Brand masih dipakai produk.');
        }

        $brand->delete();

        return back()->with('success', 'Brand dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Category $category = null): array
    {
        return [
            ...$request->validate([
                'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category?->id)],
                'parent_id' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn([$category?->id])],
                'type' => ['required', Rule::enum(CategoryType::class)],
                'size_type' => ['required', Rule::enum(SizeType::class)],
                'description' => ['nullable', 'string', 'max:500'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            ]),
            'sort_order' => $request->integer('sort_order'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
