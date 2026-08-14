<?php

namespace App\Rules;

use App\Models\SizeChartEntry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * BE-034 aturan 2: `product_variants.size_chart_entry_id` harus milik chart
 * produknya sendiri.
 *
 * Tanpa ini, seller bisa memasang entry "Alas Kaki 42" ke produk kaos.
 */
class EntryBelongsToSizeChart implements ValidationRule
{
    public function __construct(private ?int $sizeChartId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            if ($this->sizeChartId !== null) {
                $fail('Ukuran varian wajib dipilih dari tabel ukuran produk ini.');
            }

            return;
        }

        if ($this->sizeChartId === null) {
            $fail('Produk ini tidak memakai ukuran, jadi variannya tidak boleh punya label ukuran.');

            return;
        }

        $entry = SizeChartEntry::find($value);

        if (! $entry instanceof SizeChartEntry || $entry->size_chart_id !== $this->sizeChartId) {
            $fail('Ukuran yang dipilih bukan bagian dari tabel ukuran produk ini.');
        }
    }
}
