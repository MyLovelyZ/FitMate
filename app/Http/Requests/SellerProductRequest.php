<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Http\Attributes\ErrorBag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

/**
 * Validasi server formulir tambah/ubah produk di Seller Center
 * (`pages::seller-product-form`, dikirim sebagai multipart/form-data).
 *
 * Error disimpan di bag `product`, karena Livewire menimpa bag `default` saat
 * merender halaman; komponennya memindahkan bag ini ke error bag-nya sendiri.
 */
#[ErrorBag('product')]
class SellerProductRequest extends FormRequest
{
    /**
     * Hanya pemilik toko yang boleh menyimpan, dan hanya produk tokonya sendiri.
     * Produk toko lain dijawab 404 (sama dengan detail pesanan seller).
     */
    public function authorize(): bool
    {
        $product = $this->product();

        abort_if($product !== null && ! $product->isOwnedBy($this->user()), 404);

        return $this->user()->store !== null;
    }

    protected function prepareForValidation(): void
    {
        $variants = collect((array) $this->input('variants', []))
            ->map(fn ($row): array => [
                ...(array) $row,
                'sku' => filled($row['sku'] ?? null) ? strtoupper(trim((string) $row['sku'])) : null,
            ])
            ->values()
            ->all();

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'is_active' => $this->boolean('is_active'),
            'variants' => $variants,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $productId = $this->product()?->id ?? 0;

        $rules = [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'gender' => ['required', Rule::in(['men', 'women', 'unisex'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'base_price' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'compare_at_price' => ['nullable', 'integer', 'gt:base_price', 'max:100000000'],
            'is_active' => ['boolean'],

            'images' => [$this->product() ? 'nullable' : 'required', 'array', 'max:'.Product::MAX_IMAGES],
            'images.*' => [
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(2 * 1024)
                    ->dimensions(Rule::dimensions()->minWidth(300)->minHeight(300)),
            ],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer', Rule::exists('product_images', 'id')->where('product_id', $productId)],
            'primary_image_id' => ['nullable', 'integer', Rule::exists('product_images', 'id')->where('product_id', $productId)],

            'variants' => ['required', 'array', 'min:1', 'max:60'],
            'variants.*.id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $productId)],
            'variants.*.size_id' => $this->sizeRules(),
            'variants.*.color_id' => ['nullable', 'integer', Rule::exists('colors', 'id')->where('is_active', true)],
            'variants.*.price' => ['nullable', 'integer', 'min:1000', 'max:100000000'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:100000'],
        ];

        // SKU unik di seluruh tabel, kecuali milik varian itu sendiri.
        foreach ((array) $this->input('variants', []) as $index => $row) {
            $rules["variants.{$index}.sku"] = [
                'nullable', 'string', 'max:64', 'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('product_variants', 'sku')->ignore(filled($row['id'] ?? null) ? (int) $row['id'] : null),
            ];
        }

        return $rules;
    }

    /**
     * Pemeriksaan yang melibatkan beberapa field sekaligus.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $variants = collect((array) $this->input('variants', []));

                $combinations = $variants->map(fn ($row): string => ($row['size_id'] ?? '').'-'.($row['color_id'] ?? ''));
                if ($combinations->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('variants', 'Ada varian dengan kombinasi ukuran dan warna yang sama. Gabungkan stoknya jadi satu baris.');
                }

                if ($variants->pluck('sku')->filter()->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('variants', 'SKU tidak boleh dipakai dua varian.');
                }

                $removed = collect((array) $this->input('remove_images', []))->map(fn ($id): int => (int) $id);
                $existing = $this->product() ? ProductImage::where('product_id', $this->product()->id)->pluck('id') : collect();
                $total = $existing->diff($removed)->count() + count((array) $this->file('images', []));

                if ($this->product() && $total < 1) {
                    $validator->errors()->add('images', 'Produk minimal punya 1 foto.');
                } elseif ($total > Product::MAX_IMAGES) {
                    $validator->errors()->add('images', 'Maksimal '.Product::MAX_IMAGES.' foto per produk.');
                }

                if ($this->filled('primary_image_id') && $removed->contains((int) $this->input('primary_image_id'))) {
                    $validator->errors()->add('primary_image_id', 'Foto utama tidak boleh ikut dihapus.');
                }
            },
        ];
    }

    /**
     * Kolom `products` yang diisi dari formulir.
     *
     * @return array{name: string, category_id: int, gender: string, description: ?string, base_price: int, compare_at_price: ?int, is_active: bool}
     */
    public function productAttributes(): array
    {
        return [
            'name' => $this->validated('name'),
            'category_id' => (int) $this->validated('category_id'),
            'gender' => $this->validated('gender'),
            'description' => $this->validated('description'),
            'base_price' => (int) $this->validated('base_price'),
            'compare_at_price' => filled($this->validated('compare_at_price')) ? (int) $this->validated('compare_at_price') : null,
            'is_active' => (bool) $this->validated('is_active'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'integer' => ':Attribute harus berupa angka bulat.',
            'string' => ':Attribute harus berupa teks.',
            'array' => ':Attribute tidak valid.',
            'exists' => ':Attribute yang dipilih tidak tersedia.',
            'in' => ':Attribute yang dipilih tidak valid.',
            'boolean' => ':Attribute tidak valid.',
            'prohibited' => ':Attribute tidak boleh diisi.',
            'name.min' => 'Nama produk minimal :min karakter.',
            'name.max' => 'Nama produk maksimal :max karakter.',
            'description.max' => 'Deskripsi maksimal :max karakter.',
            'base_price.min' => 'Harga jual minimal Rp1.000.',
            'base_price.max' => 'Harga jual maksimal Rp100.000.000.',
            'compare_at_price.gt' => 'Harga coret harus lebih tinggi dari harga jual.',
            'compare_at_price.max' => 'Harga coret maksimal Rp100.000.000.',
            'images.required' => 'Unggah minimal 1 foto produk.',
            'images.max' => 'Maksimal :max foto per produk.',
            'images.*.file' => 'Foto #:position gagal diunggah.',
            'images.*.uploaded' => 'Foto #:position gagal diunggah. Ukuran maksimal 2 MB.',
            'images.*.image' => 'File #:position bukan gambar.',
            'images.*.mimes' => 'Foto #:position harus berformat JPG, PNG, atau WebP.',
            'images.*.max' => 'Foto #:position lebih dari 2 MB.',
            'images.*.dimensions' => 'Foto #:position minimal 300 × 300 piksel.',
            'variants.required' => 'Tambahkan minimal 1 varian.',
            'variants.min' => 'Tambahkan minimal 1 varian.',
            'variants.max' => 'Maksimal :max varian per produk.',
            'variants.*.size_id.required' => 'Ukuran varian #:position wajib dipilih.',
            'variants.*.size_id.exists' => 'Ukuran varian #:position tidak cocok dengan kategori produk.',
            'variants.*.size_id.prohibited' => 'Kategori ini tidak memakai ukuran.',
            'variants.*.stock.required' => 'Stok varian #:position wajib diisi.',
            'variants.*.stock.min' => 'Stok varian #:position tidak boleh minus.',
            'variants.*.stock.max' => 'Stok varian #:position maksimal :max.',
            'variants.*.price.min' => 'Harga varian #:position minimal Rp1.000.',
            'variants.*.price.max' => 'Harga varian #:position maksimal Rp100.000.000.',
            'variants.*.sku.max' => 'SKU varian #:position maksimal :max karakter.',
            'variants.*.sku.unique' => 'SKU varian #:position sudah dipakai produk lain.',
            'variants.*.sku.regex' => 'SKU varian #:position hanya boleh huruf, angka, titik, minus, dan garis bawah.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama produk',
            'category_id' => 'kategori',
            'gender' => 'gender',
            'description' => 'deskripsi',
            'base_price' => 'harga jual',
            'compare_at_price' => 'harga coret',
            'is_active' => 'status tayang',
            'images' => 'foto produk',
            'primary_image_id' => 'foto utama',
            'variants' => 'varian',
            'variants.*.color_id' => 'warna varian #:position',
            'variants.*.price' => 'harga varian #:position',
            'variants.*.stock' => 'stok varian #:position',
            'variants.*.sku' => 'SKU varian #:position',
        ];
    }

    /**
     * Kategori yang memakai ukuran mewajibkan ukuran dari jenis kategorinya
     * sendiri (ukuran sepatu tidak bisa dipasang ke kaos). Kategori tanpa
     * ukuran (aksesoris) melarang ukuran sama sekali.
     *
     * @return array<int, mixed>
     */
    private function sizeRules(): array
    {
        $category = Category::with('categoryType')->find($this->integer('category_id'));

        if ($category === null) {
            return ['nullable', 'integer'];
        }

        if (! $category->categoryType?->has_sizes) {
            return ['prohibited'];
        }

        return [
            'required',
            'integer',
            Rule::exists('sizes', 'id')
                ->where('category_type_id', $category->category_type_id)
                ->where('is_active', true),
        ];
    }

    private function product(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }
}
