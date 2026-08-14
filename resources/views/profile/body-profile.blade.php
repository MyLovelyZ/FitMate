<x-layouts.app title="Ukuran Badan">
    <x-ui.page-header title="Ukuran Badan" subtitle="Semakin lengkap ukuranmu, semakin akurat rekomendasinya.">
        <x-slot:actions>
            <x-ui.button size="sm" x-on:click="$dispatch('open-modal', 'body-profile-form')">Tambah Profil</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-4">
        <aside class="lg:col-span-1">
            @include('profile.partials.nav')

            @if ($profiles->isNotEmpty())
                <div class="mt-4 flex flex-col gap-1">
                    <p class="px-3 text-xs font-medium text-gray-500 uppercase">Profil Ukuran</p>

                    @foreach ($profiles as $profile)
                        <x-ui.nav-link
                            :href="route('profile.body', ['profile' => $profile->id])"
                            :active="$activeProfile?->id === $profile->id"
                        >
                            {{ $profile->profile_name }}
                            @if ($profile->is_default)
                                <span class="text-xs">(utama)</span>
                            @endif
                        </x-ui.nav-link>
                    @endforeach
                </div>
            @endif
        </aside>

        <div class="lg:col-span-3">
            {{--
                Form ini dibangun dari tabel `body_measurements` (BE-036), bukan
                dari daftar dimensi yang ditulis di sini. Menambah dimensi baru
                lewat panel admin otomatis memunculkan isiannya di halaman ini.
            --}}
            <x-ui.card :heading="$activeProfile?->profile_name ?? 'Profil Ukuran Baru'">
                <form
                    action="{{ $activeProfile ? route('profile.body.update', $activeProfile) : route('profile.body.store') }}"
                    method="POST"
                    class="flex flex-col gap-4"
                >
                    @csrf
                    @if ($activeProfile)
                        @method('PATCH')
                    @endif

                    <div class="grid max-w-2xl gap-4 sm:grid-cols-2">
                        <x-form.field name="profile_name" label="Nama Profil" hint="Contoh: Ukuran Saya, Ukuran Adik.">
                            <x-ui.input name="profile_name" :value="$activeProfile?->profile_name" />
                        </x-form.field>

                        <x-form.field name="gender" label="Jenis Kelamin" required hint="Menentukan tabel ukuran mana yang dipakai.">
                            <x-ui.select name="gender" placeholder="Pilih..." :options="$genderOptions" :selected="$activeProfile?->gender?->value" />
                        </x-form.field>
                    </div>

                    @foreach ($measurements->groupBy(fn ($measurement) => $measurement->applies_to->label()) as $groupLabel => $group)
                        <fieldset class="flex flex-col gap-3">
                            <legend class="text-sm font-medium text-gray-900">{{ $groupLabel }}</legend>

                            <div class="grid max-w-2xl gap-4 sm:grid-cols-2">
                                @foreach ($group as $measurement)
                                    <x-form.field
                                        :name="'measurements.'.$measurement->id"
                                        :label="$measurement->label.' ('.$measurement->unit.')'"
                                        :hint="$measurement->description"
                                    >
                                        <x-ui.input
                                            :name="'measurements['.$measurement->id.']'"
                                            type="number"
                                            step="0.1"
                                            min="1"
                                            max="400"
                                            :value="$values[$measurement->id] ?? null"
                                        />
                                    </x-form.field>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="is_default" value="1" class="rounded border-gray-300 text-brand-600" @checked($activeProfile?->is_default)>
                        Jadikan profil utama
                    </label>

                    <div class="flex gap-2">
                        <x-ui.button type="submit" class="self-start">Simpan Ukuran</x-ui.button>

                        @if ($activeProfile && $profiles->count() > 1)
                            <x-ui.button
                                type="submit"
                                variant="danger"
                                class="self-start"
                                form="delete-profile-{{ $activeProfile->id }}"
                            >
                                Hapus Profil
                            </x-ui.button>
                        @endif
                    </div>
                </form>

                @if ($activeProfile && $profiles->count() > 1)
                    <form id="delete-profile-{{ $activeProfile->id }}" action="{{ route('profile.body.destroy', $activeProfile) }}" method="POST" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            </x-ui.card>
        </div>
    </div>

    <x-ui.modal name="body-profile-form" title="Tambah Profil Ukuran">
        <form action="{{ route('profile.body.store') }}" method="POST" class="flex flex-col gap-4">
            @csrf

            <x-form.field name="profile_name" label="Nama Profil" required>
                <x-ui.input name="profile_name" placeholder="Ukuran Adik" />
            </x-form.field>

            <x-form.field name="gender" label="Jenis Kelamin" required>
                <x-ui.select name="gender" placeholder="Pilih..." :options="$genderOptions" />
            </x-form.field>

            <p class="text-sm text-gray-500">Angka ukurannya diisi setelah profil dibuat.</p>

            <x-ui.button type="submit">Buat Profil</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.app>
