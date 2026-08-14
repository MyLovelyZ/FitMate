<?php

namespace App\Http\Controllers\Seller;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * BE-050 & BE-051: pendaftaran dan pengelolaan profil toko.
 *
 * KEP-5 belum diputuskan; yang dipakai di sini adalah alur "seller mendaftar
 * sendiri, lalu diverifikasi admin" — toko baru berstatus `pending` dan
 * produknya belum boleh tampil sampai admin menyetujui.
 */
class StoreController extends Controller
{
    public function edit(Request $request): View
    {
        return view('seller.store.edit', [
            'store' => $request->user()->store,
            'statusOptions' => StoreStatus::options(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $request->user()->store;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'street' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:10'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('stores', 'slug')->ignore($store?->id)],
        ]);

        $validated['slug'] ??= Str::slug($validated['name']).'-'.Str::lower(Str::random(5));
        $validated = [...$validated, ...$this->uploadedImages($request, $store)];

        if ($store instanceof Store) {
            $store->update($validated);

            return back()->with('success', 'Profil toko diperbarui.');
        }

        $request->user()->store()->create([...$validated, 'status' => StoreStatus::Pending]);

        // Pemilik toko baru otomatis naik ke peran seller; tanpa ini dia tidak
        // akan bisa membuka halaman dashboard yang baru saja dia daftarkan.
        if ($request->user()->role === UserRole::User) {
            $request->user()->update(['role' => UserRole::Seller]);
        }

        return redirect()
            ->route('seller.dashboard')
            ->with('success', 'Tokomu terdaftar dan sedang menunggu verifikasi admin.');
    }

    /**
     * @return array<string, string>
     */
    private function uploadedImages(Request $request, ?Store $store): array
    {
        $paths = [];

        foreach (['logo', 'banner'] as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            if ($store?->{$field} !== null) {
                Storage::disk('public')->delete($store->{$field});
            }

            $paths[$field] = $request->file($field)->store('stores', 'public');
        }

        return $paths;
    }
}
