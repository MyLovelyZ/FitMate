<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Gender;
use App\Enums\SizeType;
use App\Http\Controllers\Controller;
use App\Models\BodyMeasurement;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Rules\MaxValueAtLeastMinValue;
use App\Services\SizeChartIntegrityChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * BE-037: CRUD standar ukuran, dijaga SizeChartPolicy (BE-033).
 *
 * Halaman ini juga menampilkan peringatan celah dan tumpang tindih antar entry
 * (BE-035) — angka di sini menentukan akurasi seluruh rekomendasi, jadi masalah
 * rentang harus terlihat sebelum tersimpan berbulan-bulan tanpa disadari.
 */
class SizeChartController extends Controller
{
    public function __construct(private SizeChartIntegrityChecker $integrity) {}

    public function index(): View
    {
        $this->authorize('viewAny', SizeChart::class);

        $charts = SizeChart::query()
            ->with('entries.measurements.bodyMeasurement')
            ->orderBy('size_type')
            ->orderBy('gender')
            ->get();

        return view('admin.size-charts.index', [
            'charts' => $charts,
            'warnings' => $charts->mapWithKeys(fn (SizeChart $chart): array => [
                $chart->id => $this->integrity->warningsFor($chart),
            ]),
            'sizeTypeOptions' => collect(SizeType::options())->except(SizeType::None->value)->all(),
            'genderOptions' => Gender::options(),
        ]);
    }

    public function show(SizeChart $sizeChart): View
    {
        $this->authorize('view', $sizeChart);

        return view('admin.size-charts.show', [
            'chart' => $sizeChart->load('entries.measurements.bodyMeasurement'),
            'measurements' => BodyMeasurement::query()->active()->forSizeType($sizeChart->size_type)->get(),
            'warnings' => $this->integrity->warningsFor($sizeChart),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SizeChart::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size_type' => [
                'required',
                Rule::enum(SizeType::class)->except([SizeType::None]),
                // Unique (size_type, gender) adalah yang mengunci "satu standar
                // global" — dua chart untuk kombinasi yang sama tidak boleh ada.
                Rule::unique('size_charts', 'size_type')->where(
                    fn ($query) => $query->where('gender', $request->input('gender')),
                ),
            ],
            'gender' => ['required', Rule::enum(Gender::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $chart = SizeChart::create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.size-charts.show', $chart)
            ->with('success', 'Tabel ukuran dibuat. Tambahkan label ukurannya sekarang.');
    }

    public function update(Request $request, SizeChart $sizeChart): RedirectResponse
    {
        $this->authorize('update', $sizeChart);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $sizeChart->update([...$validated, 'is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Tabel ukuran diperbarui.');
    }

    public function destroy(SizeChart $sizeChart): RedirectResponse
    {
        $this->authorize('delete', $sizeChart);

        if ($sizeChart->products()->exists()) {
            return back()->with('error', 'Tabel ini masih dipakai produk, tidak bisa dihapus. Nonaktifkan saja.');
        }

        $sizeChart->delete();

        return redirect()
            ->route('admin.size-charts.index')
            ->with('success', 'Tabel ukuran dihapus.');
    }

    /**
     * Tambah atau perbarui satu label ukuran beserta rentang tiap dimensinya.
     */
    public function storeEntry(Request $request, SizeChart $sizeChart): RedirectResponse
    {
        $this->authorize('update', $sizeChart);

        $validated = $request->validate([
            'label' => [
                'required', 'string', 'max:20',
                Rule::unique('size_chart_entries', 'label')
                    ->where(fn ($query) => $query->where('size_chart_id', $sizeChart->id))
                    ->ignore($request->input('entry_id')),
            ],
            'entry_id' => ['nullable', 'integer', Rule::exists('size_chart_entries', 'id')->where('size_chart_id', $sizeChart->id)],
            'sort_order' => ['required', 'integer', 'min:1', 'max:999'],
            'ranges' => ['array'],
            'ranges.*.min_value' => ['nullable', 'numeric', 'min:0', 'max:400'],
            'ranges.*.max_value' => ['nullable', 'numeric', 'min:0', 'max:400'],
        ]);

        foreach ($validated['ranges'] ?? [] as $measurementId => $range) {
            $request->validate([
                "ranges.{$measurementId}.max_value" => [new MaxValueAtLeastMinValue($range['min_value'] ?? null)],
            ]);
        }

        $entry = $sizeChart->entries()->updateOrCreate(
            ['id' => $validated['entry_id'] ?? null],
            ['label' => $validated['label'], 'sort_order' => $validated['sort_order'], 'is_active' => true],
        );

        $this->syncRanges($entry, $validated['ranges'] ?? []);

        return back()->with('success', "Ukuran {$entry->label} tersimpan.");
    }

    public function destroyEntry(SizeChart $sizeChart, SizeChartEntry $entry): RedirectResponse
    {
        $this->authorize('update', $sizeChart);

        abort_unless($entry->size_chart_id === $sizeChart->id, 404);

        if ($entry->variants()->exists()) {
            return back()->with('error', 'Ukuran ini masih dipakai varian produk. Nonaktifkan saja daripada dihapus.');
        }

        $entry->delete();

        return back()->with('success', 'Ukuran dihapus.');
    }

    /**
     * @param  array<int, array{min_value?: string|null, max_value?: string|null}>  $ranges
     */
    private function syncRanges(SizeChartEntry $entry, array $ranges): void
    {
        foreach ($ranges as $measurementId => $range) {
            $min = $range['min_value'] ?? null;
            $max = $range['max_value'] ?? null;

            if ($min === null || $max === null || $min === '' || $max === '') {
                $entry->measurements()->where('body_measurement_id', $measurementId)->delete();

                continue;
            }

            $entry->measurements()->updateOrCreate(
                ['body_measurement_id' => $measurementId],
                ['min_value' => $min, 'max_value' => $max],
            );
        }
    }
}
