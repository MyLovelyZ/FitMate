<?php

namespace Database\Seeders;

use App\Enums\MeasurementScope;
use App\Models\BodyMeasurement;
use Illuminate\Database\Seeder;

/**
 * Kamus dimensi ukur FitMate — data acuan, wajib jalan juga di produksi.
 *
 * `key` di sini dipakai di seluruh kode dan di seluruh data ukuran yang sudah
 * tersimpan. Sekali dirilis, mengubahnya berarti memigrasi data pengguna.
 */
class BodyMeasurementSeeder extends Seeder
{
    public function run(): void
    {
        $measurements = [
            ['key' => 'tinggi_badan', 'label' => 'Tinggi Badan', 'unit' => 'cm', 'applies_to' => MeasurementScope::General, 'description' => 'Ukur tanpa alas kaki, berdiri tegak menempel dinding.'],
            ['key' => 'berat_badan', 'label' => 'Berat Badan', 'unit' => 'kg', 'applies_to' => MeasurementScope::General, 'description' => 'Timbang pagi hari sebelum makan.'],
            ['key' => 'lingkar_dada', 'label' => 'Lingkar Dada', 'unit' => 'cm', 'applies_to' => MeasurementScope::Top, 'description' => 'Ukur bagian dada terlebar, pita sejajar lantai.'],
            ['key' => 'lebar_bahu', 'label' => 'Lebar Bahu', 'unit' => 'cm', 'applies_to' => MeasurementScope::Top, 'description' => 'Ukur dari ujung bahu kiri ke ujung bahu kanan lewat punggung.'],
            ['key' => 'panjang_badan', 'label' => 'Panjang Badan', 'unit' => 'cm', 'applies_to' => MeasurementScope::Top, 'description' => 'Ukur dari pangkal leher belakang sampai batas pinggul.'],
            ['key' => 'lingkar_pinggang', 'label' => 'Lingkar Pinggang', 'unit' => 'cm', 'applies_to' => MeasurementScope::Bottom, 'description' => 'Ukur di bagian pinggang terkecil, jangan ditahan napas.'],
            ['key' => 'lingkar_pinggul', 'label' => 'Lingkar Pinggul', 'unit' => 'cm', 'applies_to' => MeasurementScope::Bottom, 'description' => 'Ukur bagian pinggul terlebar.'],
            ['key' => 'panjang_kaki', 'label' => 'Panjang Kaki', 'unit' => 'cm', 'applies_to' => MeasurementScope::Bottom, 'description' => 'Ukur dari pinggang sampai mata kaki di sisi luar.'],
            ['key' => 'panjang_telapak_kaki', 'label' => 'Panjang Telapak Kaki', 'unit' => 'cm', 'applies_to' => MeasurementScope::Footwear, 'description' => 'Berdiri di atas kertas, ukur dari tumit ke ujung jari terpanjang.'],
            ['key' => 'lebar_telapak_kaki', 'label' => 'Lebar Telapak Kaki', 'unit' => 'cm', 'applies_to' => MeasurementScope::Footwear, 'description' => 'Ukur bagian telapak kaki terlebar.'],
        ];

        foreach ($measurements as $index => $measurement) {
            BodyMeasurement::updateOrCreate(
                ['key' => $measurement['key']],
                [...$measurement, 'sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
