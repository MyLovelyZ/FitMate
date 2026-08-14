<?php

use App\Enums\Gender;
use App\Models\BodyMeasurement;
use App\Models\User;
use App\Models\UserBodyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Profil ukuran badan dengan angka yang sudah terisi.
 *
 * Kuncinya memakai `body_measurements.key`, jadi test membaca sama seperti
 * bahasa domainnya: `['lingkar_dada' => 99]`. Butuh BodyMeasurementSeeder
 * sudah dijalankan lebih dulu.
 *
 * @param  array<string, float>  $values
 */
function profileWith(array $values, Gender $gender = Gender::Male): UserBodyProfile
{
    $profile = UserBodyProfile::factory()
        ->forGender($gender)
        ->for(User::factory())
        ->create();

    foreach ($values as $key => $value) {
        $profile->measurements()->create([
            'body_measurement_id' => BodyMeasurement::where('key', $key)->value('id'),
            'value' => $value,
        ]);
    }

    return $profile->load('measurements');
}
