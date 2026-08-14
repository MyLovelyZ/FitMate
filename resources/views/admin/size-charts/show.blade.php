<x-layouts.dashboard :title="$chart->name" area="admin">
    <x-ui.page-header :title="$chart->name" :subtitle="$chart->size_type->label().' · '.$chart->gender->label()">
        <x-slot:actions>
            <x-ui.button :href="route('admin.size-charts.index')" size="sm" variant="secondary">Kembali</x-ui.button>
            <x-ui.button size="sm" x-on:click="$dispatch('open-modal', 'entry-form')">Tambah Ukuran</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    @if ($warnings !== [])
        <x-ui.alert variant="warning" class="mb-6" :dismissible="false">
            <p class="font-medium">Rentang chart ini perlu ditinjau:</p>

            <ul class="mt-1 list-inside list-disc">
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="flex flex-col gap-4">
        @forelse ($chart->entries as $entry)
            @php($ranges = $entry->measurements->keyBy('body_measurement_id'))

            <x-ui.card>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-lg font-semibold">{{ $entry->label }}</span>
                        <x-ui.badge>urutan {{ $entry->sort_order }}</x-ui.badge>
                    </div>

                    <form action="{{ route('admin.size-charts.entries.destroy', [$chart, $entry]) }}" method="POST">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="text-sm text-red-600 hover:underline">Hapus ukuran</button>
                    </form>
                </div>

                <form action="{{ route('admin.size-charts.entries.store', $chart) }}" method="POST" class="flex flex-col gap-3">
                    @csrf
                    <input type="hidden" name="entry_id" value="{{ $entry->id }}">
                    <input type="hidden" name="label" value="{{ $entry->label }}">
                    <input type="hidden" name="sort_order" value="{{ $entry->sort_order }}">

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($measurements as $measurement)
                            @php($range = $ranges->get($measurement->id))

                            <div class="flex flex-col gap-1.5">
                                <label class="text-sm font-medium text-gray-900">{{ $measurement->label }} ({{ $measurement->unit }})</label>

                                <div class="flex items-center gap-2">
                                    <input
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        name="ranges[{{ $measurement->id }}][min_value]"
                                        value="{{ $range?->min_value }}"
                                        placeholder="min"
                                        class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-gray-300 ring-inset"
                                    >

                                    <span class="text-gray-400">&ndash;</span>

                                    <input
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        name="ranges[{{ $measurement->id }}][max_value]"
                                        value="{{ $range?->max_value }}"
                                        placeholder="max"
                                        class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-gray-300 ring-inset"
                                    >
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <x-ui.button type="submit" size="sm" class="self-start">Simpan Rentang {{ $entry->label }}</x-ui.button>
                </form>
            </x-ui.card>
        @empty
            <x-ui.empty-state title="Chart ini belum punya label ukuran" description="Tambahkan S, M, L, dan seterusnya, lalu isi rentang tiap dimensi.">
                <x-ui.button size="sm" x-on:click="$dispatch('open-modal', 'entry-form')">Tambah Ukuran</x-ui.button>
            </x-ui.empty-state>
        @endforelse
    </div>

    <x-ui.modal name="entry-form" title="Tambah Label Ukuran">
        <form action="{{ route('admin.size-charts.entries.store', $chart) }}" method="POST" class="flex flex-col gap-4">
            @csrf

            <x-form.field name="label" label="Label" required hint="Contoh: M untuk pakaian, 42 untuk alas kaki.">
                <x-ui.input name="label" />
            </x-form.field>

            <x-form.field name="sort_order" label="Urutan" required hint="Dari ukuran terkecil ke terbesar, mulai dari 1.">
                <x-ui.input name="sort_order" type="number" min="1" :value="$chart->entries->count() + 1" />
            </x-form.field>

            <p class="text-sm text-gray-500">Rentang tiap dimensi diisi setelah labelnya dibuat.</p>

            <x-ui.button type="submit">Tambah Ukuran</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.dashboard>
