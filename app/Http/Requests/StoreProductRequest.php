<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Rules\EntryBelongsToSizeChart;
use App\Rules\SizeChartMatchesCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi produk seller, termasuk dua aturan integritas ukuran dari BE-034
 * yang tidak bisa dijamin skema:
 *
 * 1. chart produk harus sejenis dengan `size_type` kategorinya
 * 2. tiap varian harus memakai entry milik chart produk itu sendiri
 */
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'size_chart_id' => ['nullable', 'integer', 'exists:size_charts,id'],
            'target_gender' => ['required', Rule::enum(Gender::class)],
            'base_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'weight_gram' => ['required', 'integer', 'min:1', 'max:200000'],
            'status' => ['required', Rule::enum(ProductStatus::class)->except([ProductStatus::Rejected])],

            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product?->id)],
            'variants.*.size_chart_entry_id' => ['nullable', 'integer'],
            'variants.*.sku' => ['required', 'string', 'max:100'],
            'variants.*.color_name' => ['nullable', 'string', 'max:50'],
            'variants.*.color_hex' => ['nullable', 'string', 'max:7'],
            'variants.*.price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'variants.*.is_active' => ['nullable', 'boolean'],

            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Aturan BE-034 dijalankan di sini, bukan di `rules()`.
     *
     * Alasannya penting: rule object yang dipasang bersama `nullable` akan
     * dilewati begitu nilainya null, sehingga "kategori butuh ukuran tapi
     * chart-nya kosong" justru lolos — persis lubang yang mau ditutup.
     * Callback `after` selalu jalan, apa pun isi nilainya.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateSizeIntegrity($validator),
        ];
    }

    private function validateSizeIntegrity(Validator $validator): void
    {
        $categoryId = $this->input('category_id') === null ? null : $this->integer('category_id');
        $sizeChartId = $this->input('size_chart_id') === null ? null : $this->integer('size_chart_id');

        (new SizeChartMatchesCategory($categoryId))->validate(
            'size_chart_id',
            $sizeChartId,
            fn (string $message) => $validator->errors()->add('size_chart_id', $message),
        );

        $entryRule = new EntryBelongsToSizeChart($sizeChartId);

        foreach ((array) $this->input('variants', []) as $index => $variant) {
            $entryId = ($variant['size_chart_entry_id'] ?? null) === null
                ? null
                : (int) $variant['size_chart_entry_id'];

            $entryRule->validate(
                "variants.{$index}.size_chart_entry_id",
                $entryId,
                fn (string $message) => $validator->errors()->add("variants.{$index}.size_chart_entry_id", $message),
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variants.required' => 'Produk harus punya minimal satu varian.',
            'variants.*.sku.required' => 'SKU tiap varian wajib diisi.',
        ];
    }
}
