<?php

namespace App\Http\Controllers;

use App\Models\UserAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('profile.addresses', [
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        // Alamat pertama otomatis jadi alamat utama, supaya checkout tidak
        // pernah menemukan pengguna yang punya alamat tapi tidak punya default.
        $validated['is_default'] = $validated['is_default'] || ! $request->user()->addresses()->exists();

        $request->user()->addresses()->create($validated);

        return back()->with('success', 'Alamat ditambahkan.');
    }

    public function update(Request $request, UserAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $address->update($this->validated($request));

        return back()->with('success', 'Alamat diperbarui.');
    }

    public function setDefault(Request $request, UserAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $address->update(['is_default' => true]);

        return back()->with('success', 'Alamat utama diganti.');
    }

    public function destroy(Request $request, UserAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $address->delete();

        // Kalau yang dihapus adalah alamat utama, promosikan alamat tersisa
        // supaya tidak ada pengguna yang kehilangan alamat default diam-diam.
        $remaining = $request->user()->addresses()->orderBy('id')->first();

        if ($remaining !== null && ! $request->user()->addresses()->where('is_default', true)->exists()) {
            $remaining->update(['is_default' => true]);
        }

        return back()->with('success', 'Alamat dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return [
            ...$request->validate([
                'label' => ['nullable', 'string', 'max:50'],
                'recipient_name' => ['required', 'string', 'max:255'],
                'recipient_phone' => ['required', 'string', 'max:20'],
                'street' => ['required', 'string', 'max:500'],
                'city' => ['required', 'string', 'max:100'],
                'state' => ['required', 'string', 'max:100'],
                'postal_code' => ['required', 'string', 'max:10'],
                'country' => ['nullable', 'string', 'max:100'],
            ]),
            'label' => $request->input('label') ?: 'Rumah',
            'country' => $request->input('country') ?: 'Indonesia',
            'is_default' => $request->boolean('is_default'),
        ];
    }

    private function authorizeAddress(Request $request, UserAddress $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 403);
    }
}
