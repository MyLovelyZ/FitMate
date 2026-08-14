<x-layouts.dashboard title="Standar Ukuran" area="admin">
    <x-ui.page-header title="Standar Ukuran FitMate" subtitle="Angka di sini menentukan akurasi seluruh rekomendasi ukuran.">
        <x-slot:actions>
            <x-ui.button size="sm" x-on:click="$dispatch('open-modal', 'size-chart-form')">Tambah Chart</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    <x-ui.alert variant="info" class="mb-6" :dismissible="false">
        Satu kombinasi jenis ukuran dan gender hanya boleh punya satu chart. Itulah yang membuat "XL" bernilai sama di
        seluruh toko — jangan dilonggarkan.
    </x-ui.alert>

    @if ($charts->isEmpty())
        <x-ui.empty-state title="Belum ada standar ukuran" description="Buat chart pertama, lalu isi label ukuran dan rentangnya.">
            <x-ui.button size="sm" x-on:click="$dispatch('open-modal', 'size-chart-form')">Tambah Chart</x-ui.button>
        </x-ui.empty-state>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($charts as $chart)
                <x-ui.card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex flex-col gap-1">
                            <a href="{{ route('admin.size-charts.show', $chart) }}" class="font-medium hover:text-brand-600">
                                {{ $chart->name }}
                            </a>

                            <div class="flex flex-wrap gap-2">
                                <x-ui.badge>{{ $chart->size_type->label() }}</x-ui.badge>
                                <x-ui.badge>{{ $chart->gender->label() }}</x-ui.badge>
                                <x-ui.badge :variant="$chart->is_active ? 'success' : 'gray'">
                                    {{ $chart->is_active ? 'Aktif' : 'Nonaktif' }}
                                </x-ui.badge>
                                <x-ui.badge>{{ $chart->entries->count() }} ukuran</x-ui.badge>
                            </div>
                        </div>

                        <x-ui.button :href="route('admin.size-charts.show', $chart)" size="sm" variant="secondary">Kelola Rentang</x-ui.button>
                    </div>

                    {{-- BE-035: celah dan tumpang tindih harus terlihat, bukan diam-diam tersimpan. --}}
                    @if (($warnings[$chart->id] ?? []) !== [])
                        <div class="mt-3 flex flex-col gap-1 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                            @foreach ($warnings[$chart->id] as $warning)
                                <p>{{ $warning }}</p>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    @endif

    <x-ui.modal name="size-chart-form" title="Tambah Standar Ukuran">
        <form action="{{ route('admin.size-charts.store') }}" method="POST" class="flex flex-col gap-4">
            @csrf

            <x-form.field name="name" label="Nama Chart" required hint="Contoh: Atasan Pria, Alas Kaki Unisex.">
                <x-ui.input name="name" />
            </x-form.field>

            <x-form.field name="size_type" label="Jenis Ukuran" required>
                <x-ui.select name="size_type" placeholder="Pilih jenis" :options="$sizeTypeOptions" />
            </x-form.field>

            <x-form.field name="gender" label="Gender" required>
                <x-ui.select name="gender" placeholder="Pilih gender" :options="$genderOptions" />
            </x-form.field>

            <x-form.field name="description" label="Keterangan">
                <x-ui.textarea name="description" rows="2" />
            </x-form.field>

            <x-ui.button type="submit">Simpan Chart</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.dashboard>
