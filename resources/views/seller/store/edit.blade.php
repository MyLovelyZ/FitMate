<x-layouts.dashboard title="Profil Toko" area="seller">
    <x-ui.page-header :title="$store ? 'Profil Toko' : 'Daftarkan Toko'" :subtitle="$store ? null : 'Isi data toko untuk mulai berjualan. Admin akan memverifikasinya.'">
        @if ($store)
            <x-slot:actions>
                <x-ui.badge :variant="$store->isActive() ? 'success' : 'warning'">{{ $store->status->label() }}</x-ui.badge>
            </x-slot>
        @endif
    </x-ui.page-header>

    <x-ui.card heading="Informasi Toko">
        <form action="{{ route('seller.store.update') }}" method="POST" enctype="multipart/form-data" class="flex max-w-2xl flex-col gap-4">
            @csrf
            @method('PATCH')

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field name="name" label="Nama Toko" required class="sm:col-span-2">
                    <x-ui.input name="name" :value="$store?->name" />
                </x-form.field>

                <x-form.field name="description" label="Deskripsi" class="sm:col-span-2">
                    <x-ui.textarea name="description" :value="$store?->description" />
                </x-form.field>

                <x-form.field name="phone_number" label="Nomor HP Toko">
                    <x-ui.input name="phone_number" type="tel" :value="$store?->phone_number" />
                </x-form.field>

                <x-form.field name="email" label="Email Toko">
                    <x-ui.input name="email" type="email" :value="$store?->email" />
                </x-form.field>
            </div>

            <fieldset class="flex flex-col gap-4">
                <legend class="text-sm font-medium text-gray-900">Alamat Asal Pengiriman</legend>

                <x-form.field name="street" label="Alamat" required>
                    <x-ui.textarea name="street" rows="2" :value="$store?->street" />
                </x-form.field>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.field name="city" label="Kota" required>
                        <x-ui.input name="city" :value="$store?->city" />
                    </x-form.field>

                    <x-form.field name="state" label="Provinsi" required>
                        <x-ui.input name="state" :value="$store?->state" />
                    </x-form.field>

                    <x-form.field name="postal_code" label="Kode Pos" required>
                        <x-ui.input name="postal_code" :value="$store?->postal_code" />
                    </x-form.field>
                </div>
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field name="logo" label="Logo">
                    <input type="file" name="logo" accept="image/*" class="text-sm">
                </x-form.field>

                <x-form.field name="banner" label="Banner">
                    <input type="file" name="banner" accept="image/*" class="text-sm">
                </x-form.field>
            </div>

            <x-ui.button type="submit" class="self-start">{{ $store ? 'Simpan' : 'Daftarkan Toko' }}</x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.dashboard>
