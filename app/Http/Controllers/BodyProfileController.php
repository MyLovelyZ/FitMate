<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Models\BodyMeasurement;
use App\Models\UserBodyProfile;
use App\Services\SizeRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BE-036: pengelolaan ukuran badan pengguna.
 *
 * Form-nya dibangun dari isi tabel `body_measurements`, tidak di-hardcode —
 * itulah gunanya kamus dimensi. Menambah dimensi baru cukup lewat seeder atau
 * panel admin, halaman ini otomatis ikut.
 */
class BodyProfileController extends Controller
{
    public function __construct(private SizeRecommendationService $recommendations) {}

    public function index(Request $request): View
    {
        $profiles = $request->user()
            ->bodyProfiles()
            ->with('measurements')
            ->orderByDesc('is_default')
            ->get();

        $active = $profiles->firstWhere('id', $request->integer('profile')) ?? $profiles->first();

        return view('profile.body-profile', [
            'profiles' => $profiles,
            'activeProfile' => $active,
            'measurements' => BodyMeasurement::query()->active()->orderBy('sort_order')->get(),
            'values' => $active?->measurementValues() ?? [],
            'genderOptions' => collect(Gender::options())->except(Gender::Unisex->value)->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $profile = $request->user()->bodyProfiles()->create([
            'profile_name' => $validated['profile_name'],
            'gender' => $validated['gender'],
            'is_default' => $validated['is_default'] || ! $request->user()->bodyProfiles()->exists(),
        ]);

        $this->syncMeasurements($profile, $validated['measurements']);

        return redirect()
            ->route('profile.body', ['profile' => $profile->id])
            ->with('success', 'Profil ukuran tersimpan.');
    }

    public function update(Request $request, UserBodyProfile $bodyProfile): RedirectResponse
    {
        $this->authorizeProfile($request, $bodyProfile);

        $validated = $this->validated($request);

        $bodyProfile->update([
            'profile_name' => $validated['profile_name'],
            'gender' => $validated['gender'],
            'is_default' => $validated['is_default'],
        ]);

        $this->syncMeasurements($bodyProfile, $validated['measurements']);

        return redirect()
            ->route('profile.body', ['profile' => $bodyProfile->id])
            ->with('success', 'Ukuran badanmu diperbarui. Rekomendasi ukuran ikut dihitung ulang.');
    }

    public function destroy(Request $request, UserBodyProfile $bodyProfile): RedirectResponse
    {
        $this->authorizeProfile($request, $bodyProfile);

        $bodyProfile->delete();

        return redirect()
            ->route('profile.body')
            ->with('success', 'Profil ukuran dihapus.');
    }

    /**
     * Simpan angka per dimensi, lalu buang cache rekomendasi profil ini.
     *
     * Tanpa invalidasi itu, pengguna yang baru memperbarui ukurannya akan terus
     * melihat saran ukuran yang lama (BE-032).
     *
     * @param  array<int, float|null>  $measurements
     */
    private function syncMeasurements(UserBodyProfile $profile, array $measurements): void
    {
        foreach ($measurements as $measurementId => $value) {
            if ($value === null || $value === '') {
                $profile->measurements()->where('body_measurement_id', $measurementId)->delete();

                continue;
            }

            $profile->measurements()->updateOrCreate(
                ['body_measurement_id' => $measurementId],
                ['value' => $value],
            );
        }

        $this->recommendations->invalidateFor($profile);
    }

    /**
     * @return array{profile_name: string, gender: string, is_default: bool, measurements: array<int, float|null>}
     */
    private function validated(Request $request): array
    {
        $validIds = BodyMeasurement::query()->active()->pluck('id')->all();

        $validated = $request->validate([
            'profile_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', 'string', 'in:male,female'],
            'is_default' => ['nullable', 'boolean'],
            'measurements' => ['array'],
            'measurements.*' => ['nullable', 'numeric', 'min:1', 'max:400'],
        ]);

        /** @var array<int, float|null> $measurements */
        $measurements = collect($validated['measurements'] ?? [])
            ->only($validIds)
            ->all();

        return [
            'profile_name' => $validated['profile_name'] ?: 'Profil Saya',
            'gender' => $validated['gender'],
            'is_default' => $request->boolean('is_default'),
            'measurements' => $measurements,
        ];
    }

    private function authorizeProfile(Request $request, UserBodyProfile $profile): void
    {
        abort_unless($profile->user_id === $request->user()->id, 403);
    }
}
