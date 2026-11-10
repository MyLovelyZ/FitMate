<?php

namespace App\Http\Controllers;

use App\Http\Requests\SellerProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Menerima kiriman formulir produk Seller Center. Halaman formulirnya sendiri
 * adalah `pages::seller-product-form`; form itu dikirim sebagai POST biasa
 * (`multipart/form-data`) karena membawa file foto, lalu divalidasi di server
 * oleh `SellerProductRequest`. Kalau gagal, Laravel mengembalikan ke formulir
 * dengan pesan error dan isian lama (`old()`).
 */
class SellerProductController extends Controller
{
    public function store(SellerProductRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request): Product {
            $product = $request->user()->store->products()->create([
                ...$request->productAttributes(),
                'slug' => Product::uniqueSlugFor($request->validated('name')),
            ]);

            $product->syncVariants($request->validated('variants'));
            $product->addImages($request->file('images', []));

            return $product;
        });

        return redirect()
            ->route('seller.products.index')
            ->with('notify', "Produk \"{$product->name}\" berhasil ditambahkan.");
    }

    public function update(SellerProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $product->update($request->productAttributes());
            $product->syncVariants($request->validated('variants'));
            $product->removeImages($request->validated('remove_images', []));
            $product->addImages($request->file('images', []));

            if ($request->filled('primary_image_id')) {
                $product->setPrimaryImage((int) $request->validated('primary_image_id'));
            }
        });

        return redirect()
            ->route('seller.products.index')
            ->with('notify', "Perubahan \"{$product->name}\" tersimpan.");
    }
}
