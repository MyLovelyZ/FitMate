<?php

/**
 * BE-034: lubang paling berbahaya di skema.
 *
 * Dua aturan yang hanya bisa ditegakkan di kode:
 * 1. `products.size_chart_id` harus sejenis dengan `category->size_type`
 * 2. `product_variants.size_chart_entry_id` harus milik chart produknya
 *
 * Tanpa keduanya, seller bisa memasang entry "Alas Kaki 42" ke produk kaos dan
 * seluruh janji standarisasi bocor lewat situ.
 */

use App\Enums\Gender;
use App\Enums\ProductStatus;
use App\Enums\SizeType;
use App\Models\BodyMeasurement;
use App\Models\Category;
use App\Models\Product;
use App\Models\SizeChart;
use App\Models\Store;
use App\Models\User;
use App\Services\SizeChartIntegrityChecker;
use Database\Seeders\BodyMeasurementSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SizeChartSeeder;

beforeEach(function () {
    $this->seed([BodyMeasurementSeeder::class, CategorySeeder::class, SizeChartSeeder::class]);

    $this->seller = User::factory()->seller()->create();
    $this->store = Store::factory()->active()->for($this->seller, 'owner')->create();

    $this->topChart = SizeChart::firstWhere(['size_type' => SizeType::Top, 'gender' => Gender::Male]);
    $this->footwearChart = SizeChart::firstWhere(['size_type' => SizeType::Footwear, 'gender' => Gender::Unisex]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productPayload(array $overrides = []): array
{
    return [
        'name' => 'Kaos Uji',
        'category_id' => Category::firstWhere('slug', 'kaos')->id,
        'target_gender' => Gender::Unisex->value,
        'base_price' => 100000,
        'weight_gram' => 300,
        'status' => ProductStatus::Draft->value,
        'variants' => [[
            'sku' => 'SKU-UJI-1',
            'price' => 100000,
            'stock' => 5,
        ]],
        ...$overrides,
    ];
}

it('menolak chart yang jenisnya tidak cocok dengan kategori', function () {
    $payload = productPayload([
        'size_chart_id' => $this->footwearChart->id,
        'variants' => [['sku' => 'SKU-1', 'price' => 100000, 'stock' => 5, 'size_chart_entry_id' => $this->footwearChart->entries->first()->id]],
    ]);

    $this->actingAs($this->seller)
        ->post(route('seller.products.store'), $payload)
        ->assertSessionHasErrors('size_chart_id');

    expect(Product::count())->toBe(0);
});

it('menolak varian yang memakai entry dari chart lain', function () {
    $payload = productPayload([
        'size_chart_id' => $this->topChart->id,
        'variants' => [[
            'sku' => 'SKU-1',
            'price' => 100000,
            'stock' => 5,
            // Entry milik chart alas kaki, dipasang ke produk kaos.
            'size_chart_entry_id' => $this->footwearChart->entries->first()->id,
        ]],
    ]);

    $this->actingAs($this->seller)
        ->post(route('seller.products.store'), $payload)
        ->assertSessionHasErrors('variants.0.size_chart_entry_id');

    expect(Product::count())->toBe(0);
});

it('mewajibkan chart untuk kategori yang memakai ukuran', function () {
    $this->actingAs($this->seller)
        ->post(route('seller.products.store'), productPayload(['size_chart_id' => null]))
        ->assertSessionHasErrors('size_chart_id');
});

it('menolak chart pada kategori aksesoris yang tidak memakai ukuran', function () {
    $payload = productPayload([
        'name' => 'Topi Uji',
        'category_id' => Category::firstWhere('slug', 'topi')->id,
        'size_chart_id' => $this->topChart->id,
    ]);

    $this->actingAs($this->seller)
        ->post(route('seller.products.store'), $payload)
        ->assertSessionHasErrors('size_chart_id');
});

it('menerima produk yang rantai ukurannya benar', function () {
    $payload = productPayload([
        'size_chart_id' => $this->topChart->id,
        'variants' => [[
            'sku' => 'SKU-BENAR-1',
            'price' => 100000,
            'stock' => 5,
            'size_chart_entry_id' => $this->topChart->entries->firstWhere('label', 'M')->id,
        ]],
    ]);

    $this->actingAs($this->seller)
        ->post(route('seller.products.store'), $payload)
        ->assertSessionHasNoErrors();

    $product = Product::firstWhere('name', 'Kaos Uji');

    expect($product)->not->toBeNull()
        ->and($product->store_id)->toBe($this->store->id)
        ->and($product->variants->first()->sizeChartEntry->label)->toBe('M');
});

it('memperingatkan admin ketika rentang antar ukuran punya celah', function () {
    $chart = SizeChart::factory()->forTops(Gender::Female)->create(['size_type' => SizeType::Top, 'gender' => Gender::Unisex]);
    $measurementId = BodyMeasurement::where('key', 'lingkar_dada')->value('id');

    $small = $chart->entries()->create(['label' => 'S', 'sort_order' => 1, 'is_active' => true]);
    $large = $chart->entries()->create(['label' => 'L', 'sort_order' => 2, 'is_active' => true]);

    $small->measurements()->create(['body_measurement_id' => $measurementId, 'min_value' => 88, 'max_value' => 92]);
    // Celah 4 cm: orang berdada 94 tidak akan pernah dapat rekomendasi.
    $large->measurements()->create(['body_measurement_id' => $measurementId, 'min_value' => 96, 'max_value' => 100]);

    $warnings = app(SizeChartIntegrityChecker::class)->warningsFor($chart->fresh());

    expect($warnings)->not->toBeEmpty()
        ->and(implode(' ', $warnings))->toContain('celah');
});
