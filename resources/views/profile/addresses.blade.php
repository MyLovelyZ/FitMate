<x-layouts.app title="Alamat Saya">
    <x-ui.page-header title="Alamat Saya" subtitle="Alamat utama dipakai otomatis saat checkout.">
        <x-slot:actions>
            <x-ui.button size="sm" x-on:click="$dispatch('open-modal', 'address-form')">Tambah Alamat</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-4">
        <aside class="lg:col-span-1">
            @include('profile.partials.nav')
        </aside>

        <div class="flex flex-col gap-4 lg:col-span-3">
            @if ($addresses->isEmpty())
                <x-ui.empty-state title="Belum ada alamat" description="Tambahkan alamat agar bisa checkout.">
                    <x-ui.button size="sm" x-on:click="$dispatch('open-modal', 'address-form')">Tambah Alamat</x-ui.button>
                </x-ui.empty-state>
            @else
                @foreach ($addresses as $address)
                    <x-ui.card>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ $address->label }}</span>

                                    @if ($address->is_default)
                                        <x-ui.badge variant="brand">Alamat Utama</x-ui.badge>
                                    @endif
                                </div>

                                <p class="text-sm text-gray-700">{{ $address->recipient_name }} &middot; {{ $address->recipient_phone }}</p>
                                <p class="text-sm text-gray-500">{{ $address->fullAddress() }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                @unless ($address->is_default)
                                    <form action="{{ route('profile.addresses.default', $address) }}" method="POST">
                                        @csrf
                                        <x-ui.button type="submit" size="sm" variant="secondary">Jadikan Utama</x-ui.button>
                                    </form>
                                @endunless

                                <form action="{{ route('profile.addresses.destroy', $address) }}" method="POST">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="text-sm text-red-600 hover:underline">Hapus</button>
                                </form>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            @endif
        </div>
    </div>

    <x-ui.modal name="address-form" title="Tambah Alamat">
        <form action="{{ route('profile.addresses.store') }}" method="POST" class="flex flex-col gap-4">
            @csrf

            <x-form.field name="label" label="Label" hint="Contoh: Rumah, Kantor.">
                <x-ui.input name="label" placeholder="Rumah" />
            </x-form.field>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field name="recipient_name" label="Nama Penerima" required>
                    <x-ui.input name="recipient_name" />
                </x-form.field>

                <x-form.field name="recipient_phone" label="Nomor HP Penerima" required>
                    <x-ui.input name="recipient_phone" type="tel" />
                </x-form.field>
            </div>

            <x-form.field name="street" label="Alamat Lengkap" required>
                <x-ui.textarea name="street" rows="3" />
            </x-form.field>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field name="city" label="Kota" required>
                    <x-ui.input name="city" />
                </x-form.field>

                <x-form.field name="state" label="Provinsi" required>
                    <x-ui.input name="state" />
                </x-form.field>

                <x-form.field name="postal_code" label="Kode Pos" required>
                    <x-ui.input name="postal_code" />
                </x-form.field>

                <x-form.field name="country" label="Negara">
                    <x-ui.input name="country" placeholder="Indonesia" />
                </x-form.field>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="is_default" value="1" class="rounded border-gray-300 text-brand-600">
                Jadikan alamat utama
            </label>

            <x-ui.button type="submit">Simpan Alamat</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.app>
