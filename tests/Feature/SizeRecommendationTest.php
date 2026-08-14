<?php

/**
 * BE-038: test menyeluruh mesin ukuran.
 *
 * Skenario yang wajib tertutup: badan pas di tengah rentang, pas di batas
 * (`min` dan `max` persis), di antara dua ukuran, di luar semua rentang,
 * dimensi tidak lengkap, dan produk tanpa ukuran.
 */

use App\Enums\FitStatus;
use App\Enums\Gender;
use App\Enums\SizeType;
use App\Models\Category;
use App\Models\Product;
use App\Models\SizeChart;
use App\Models\SizeRecommendation;
use App\Services\SizeRecommendationService;
use Database\Seeders\BodyMeasurementSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SizeChartSeeder;

beforeEach(function () {
    $this->seed([BodyMeasurementSeeder::class, CategorySeeder::class, SizeChartSeeder::class]);

    $this->service = app(SizeRecommendationService::class);
    $this->chart = SizeChart::firstWhere(['size_type' => SizeType::Top, 'gender' => Gender::Male]);
});

it('menyarankan ukuran ketika seluruh dimensi jatuh di tengah rentang', function () {
    // Rentang L pria: dada 97–102, bahu 45–47, panjang 70–72, tinggi 170–175.
    $profile = profileWith([
        'lingkar_dada' => 99,
        'lebar_bahu' => 46,
        'panjang_badan' => 71,
        'tinggi_badan' => 172,
    ]);

    $result = $this->service->forChart($profile, $this->chart);

    expect($result->label())->toBe('L')
        ->and($result->fitScore)->toBe(100.0)
        ->and($result->fitStatus)->toBe(FitStatus::Fit)
        ->and($result->matchedMeasurements)->toBe($result->totalMeasurements);
});

it('memperlakukan nilai tepat di batas rentang sebagai cocok', function (float $chest) {
    $profile = profileWith([
        'lingkar_dada' => $chest,
        'lebar_bahu' => 46,
        'panjang_badan' => 71,
        'tinggi_badan' => 172,
    ]);

    $result = $this->service->forChart($profile, $this->chart);

    expect($result->label())->toBe('L')
        ->and($result->fitScore)->toBe(100.0);
})->with([
    'batas bawah' => 97.0,
    'batas atas' => 102.0,
]);

it('memilih ukuran dengan simpangan terkecil ketika badan berada di antara dua ukuran', function () {
    // Dada 104 masuk XL, tapi bahu dan panjang masih di wilayah L.
    $profile = profileWith([
        'lingkar_dada' => 104,
        'lebar_bahu' => 46,
        'panjang_badan' => 71,
        'tinggi_badan' => 172,
    ]);

    $result = $this->service->forChart($profile, $this->chart);

    expect($result->label())->toBe('L')
        ->and($result->fitScore)->toBe(75.0)
        ->and($result->mismatches())->toHaveCount(1)
        ->and($result->mismatches()[0]->status)->toBe(FitStatus::Loose);
});

it('tetap menghitung ketika pengguna baru mengisi sebagian dimensi', function () {
    $profile = profileWith(['lingkar_dada' => 99]);

    $result = $this->service->forChart($profile, $this->chart);

    expect($result->label())->toBe('L')
        ->and($result->totalMeasurements)->toBe(1)
        ->and($result->comparisons)->toHaveCount(1);
});

it('menandai badan yang berada di luar seluruh rentang', function () {
    $profile = profileWith([
        'lingkar_dada' => 60,
        'lebar_bahu' => 25,
        'panjang_badan' => 40,
        'tinggi_badan' => 120,
    ]);

    $result = $this->service->forChart($profile, $this->chart);

    expect($result->hasSuggestion())->toBeFalse()
        ->and($result->fitScore)->toBe(0.0)
        ->and($result->fitStatus)->toBe(FitStatus::Tight);
});

it('tidak menghitung apa pun ketika pengguna belum mengisi ukuran sama sekali', function () {
    $profile = profileWith([]);

    expect($this->service->forChart($profile, $this->chart))->toBeNull();
});

it('berhenti pada produk kategori tanpa ukuran', function () {
    $profile = profileWith(['lingkar_dada' => 99]);

    $product = Product::factory()->active()->create([
        'category_id' => Category::firstWhere('slug', 'topi')->id,
        'size_chart_id' => null,
    ]);

    expect($this->service->forProduct($profile, $product))->toBeNull()
        ->and($this->service->resolveChartFor($product, $profile))->toBeNull();
});

it('memakai chart yang dipasang seller pada produknya', function () {
    $profile = profileWith(['lingkar_dada' => 99]);

    $product = Product::factory()->active()->create([
        'category_id' => Category::firstWhere('slug', 'kaos')->id,
        'size_chart_id' => $this->chart->id,
    ]);

    $result = $this->service->forProduct($profile, $product);

    expect($result->chart->id)->toBe($this->chart->id)
        ->and($result->label())->toBe('L');
});

it('menyimpan hasil hitung ke size_recommendations mengikuti unique key-nya', function () {
    $profile = profileWith(['lingkar_dada' => 99]);

    $this->service->forChart($profile, $this->chart);
    $this->service->forChart($profile, $this->chart);

    expect(SizeRecommendation::where('user_body_profile_id', $profile->id)->count())->toBe(1);
});

it('membuang cache rekomendasi ketika ukuran badan berubah', function () {
    $profile = profileWith(['lingkar_dada' => 99]);

    $this->service->forChart($profile, $this->chart);
    expect(SizeRecommendation::where('user_body_profile_id', $profile->id)->exists())->toBeTrue();

    $this->service->invalidateFor($profile);
    expect(SizeRecommendation::where('user_body_profile_id', $profile->id)->exists())->toBeFalse();
});

it('memilih chart sesuai gender profil ketika produk belum punya chart', function () {
    $profile = profileWith(['lingkar_dada' => 84], Gender::Female);

    $product = Product::factory()->active()->create([
        'category_id' => Category::firstWhere('slug', 'kaos')->id,
        'size_chart_id' => null,
    ]);

    $chart = $this->service->resolveChartFor($product, $profile);

    expect($chart->gender)->toBe(Gender::Female)
        ->and($chart->size_type)->toBe(SizeType::Top);
});
