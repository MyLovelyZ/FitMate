<x-layouts.app title="Ukuran Badan">
    <x-ui.page-header title="Ukuran Badan" subtitle="Semakin lengkap ukuranmu, semakin akurat rekomendasinya.">
        <x-slot:actions>
            <x-ui.button size="sm">Tambah Profil</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    {{--
        TODO(BE-036): form ini WAJIB dibangun dinamis dari tabel body_measurements.
        Jangan hardcode nama dimensi — itu justru alasan kamus dimensi dibuat.
    --}}
    <x-ui.card heading="Profil Utama">
        <form action="{{ route('profile.body') }}" method="POST" class="grid max-w-2xl gap-4 sm:grid-cols-2">
            @csrf

            <x-form.field name="label" label="Nama Profil" hint="Contoh: Ukuran Saya, Ukuran Adik.">
                <x-ui.input name="label" />
            </x-form.field>

            <x-form.field name="gender" label="Jenis Kelamin" required>
                <x-ui.select name="gender" placeholder="Pilih..." :options="['male' => 'Laki-laki', 'female' => 'Perempuan']" />
            </x-form.field>

            {{-- @foreach ($bodyMeasurements as $measurement) --}}
            <x-form.field name="height" label="Tinggi Badan (cm)">
                <x-ui.input name="height" type="number" step="0.1" />
            </x-form.field>

            <x-form.field name="weight" label="Berat Badan (kg)">
                <x-ui.input name="weight" type="number" step="0.1" />
            </x-form.field>
            {{-- @endforeach --}}

            <x-ui.button type="submit" class="self-start sm:col-span-2">Simpan Ukuran</x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.app>
