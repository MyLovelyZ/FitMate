<?php

namespace App\Rules;

use App\Enums\SizeType;
use App\Models\Category;
use App\Models\SizeChart;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * BE-034 aturan 1: `products.size_chart_id` harus punya `size_type` yang sama
 * dengan `category->size_type`.
 *
 * Skema tidak bisa menjamin ini. Tanpa aturan ini, seller bisa memasang chart
 * "Alas Kaki" ke produk kaos, dan seluruh janji standarisasi bocor lewat situ.
 */
class SizeChartMatchesCategory implements ValidationRule
{
    public function __construct(private ?int $categoryId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $category = Category::find($this->categoryId);

        if (! $category instanceof Category) {
            return;
        }

        if (! $category->requiresSizing()) {
            if ($value !== null) {
                $fail("Kategori {$category->name} tidak memakai ukuran, jadi tidak boleh dipasangi tabel ukuran.");
            }

            return;
        }

        if ($value === null) {
            $fail("Kategori {$category->name} wajib memakai tabel ukuran standar FitMate.");

            return;
        }

        $chart = SizeChart::find($value);

        if (! $chart instanceof SizeChart) {
            $fail('Tabel ukuran yang dipilih tidak ditemukan.');

            return;
        }

        if ($chart->size_type !== $category->size_type) {
            $fail(sprintf(
                'Tabel ukuran "%s" berjenis %s, sedangkan kategori %s butuh jenis %s.',
                $chart->name,
                $chart->size_type->label(),
                $category->name,
                ($category->size_type ?? SizeType::None)->label(),
            ));
        }
    }
}
