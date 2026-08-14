{{--
    Layar utama FitMate (BE-062).

    `$recommendation` berasal dari SizeRecommendationService dan boleh null.
    Empat keadaan yang ditangani di sini: belum masuk, sudah masuk tapi belum
    mengisi ukuran, produk tanpa ukuran, dan hasil hitung yang sebenarnya.
--}}
<x-ui.card class="border-brand-200 bg-brand-50">
    @guest
        <div class="flex flex-col items-start gap-3">
            <p class="text-sm text-gray-700">Masuk dan isi ukuran badanmu untuk melihat ukuran yang pas.</p>
            <x-ui.button :href="route('login')" size="sm">Masuk</x-ui.button>
        </div>
    @endguest

    @auth
        @if (! $product->requiresSizing() && $sizeChart === null)
            <p class="text-sm text-gray-700">Produk ini tidak memakai ukuran badan, jadi tidak ada yang perlu dihitung.</p>
        @elseif ($recommendation === null)
            <div class="flex flex-col items-start gap-3">
                <p class="text-sm text-gray-700">
                    Ukuran badanmu belum lengkap, jadi FitMate belum bisa menyarankan ukuran untuk produk ini.
                </p>

                <x-ui.button :href="route('profile.body')" size="sm">Isi Ukuran Badan</x-ui.button>
            </div>
        @else
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex flex-col gap-1">
                        <p class="text-sm text-gray-600">Ukuran yang disarankan untukmu</p>
                        <p class="text-2xl font-bold text-brand-700">{{ $recommendation->label() ?? '—' }}</p>
                    </div>

                    <x-product.fit-badge :status="$recommendation->fitStatus->value" />
                </div>

                @if (! $recommendation->hasSuggestion())
                    <p class="text-sm text-amber-700">
                        Ukuran badanmu berada di luar seluruh rentang yang tersedia di tabel ini. Cek tabel ukuran di bawah
                        sebelum memutuskan.
                    </p>
                @else
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <span>Tingkat kecocokan {{ number_format($recommendation->fitScore, 0) }}%</span>
                        <span class="text-gray-300">|</span>
                        <span>{{ $recommendation->matchedMeasurements }} dari {{ $recommendation->totalMeasurements }} dimensi masuk rentang</span>
                    </div>

                    @unless ($recommendation->isConfident())
                        <p class="text-sm text-amber-700">
                            Kecocokannya rendah. Sebaiknya cek dulu rincian per dimensi di bawah sebelum membeli.
                        </p>
                    @endunless
                @endif

                @if ($recommendation->comparisons !== [])
                    <ul class="flex flex-col gap-1 text-sm">
                        @foreach ($recommendation->comparisons as $comparison)
                            <li class="flex items-start gap-2">
                                <span @class([
                                    'mt-1.5 size-1.5 shrink-0 rounded-full',
                                    'bg-green-500' => $comparison->matches(),
                                    'bg-amber-500' => ! $comparison->matches(),
                                ])></span>

                                <span class="text-gray-700">
                                    <span class="font-medium">{{ $comparison->measurement->label }}:</span>
                                    {{ $comparison->explanation() }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="flex flex-wrap gap-2">
                    @if ($sizeChart)
                        <x-ui.button size="sm" variant="secondary" x-on:click="$dispatch('open-modal', 'size-chart')">Lihat Tabel Ukuran</x-ui.button>
                    @endif

                    <x-ui.button size="sm" variant="ghost" :href="route('profile.body')">Perbarui Ukuran Badan</x-ui.button>
                </div>
            </div>
        @endif
    @endauth
</x-ui.card>

@if ($sizeChart)
    @php
        $chartMeasurements = $sizeChart->entries
            ->flatMap(fn ($entry) => $entry->measurements)
            ->map(fn ($range) => $range->bodyMeasurement)
            ->unique('id')
            ->sortBy('sort_order')
            ->values();
    @endphp

    <x-ui.modal name="size-chart" :title="'Tabel Ukuran — '.$sizeChart->name" max-width="max-w-3xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="py-2 pr-4">Ukuran</th>

                        @foreach ($chartMeasurements as $measurement)
                            <th class="py-2 pr-4">{{ $measurement->label }} ({{ $measurement->unit }})</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @foreach ($sizeChart->entries as $entry)
                        @php($ranges = $entry->measurements->keyBy('body_measurement_id'))

                        <tr @class(['bg-brand-50' => $entry->id === $recommendation?->entry?->id])>
                            <td class="py-2 pr-4 font-medium">{{ $entry->label }}</td>

                            @foreach ($chartMeasurements as $measurement)
                                @php($range = $ranges->get($measurement->id))

                                <td class="py-2 pr-4">
                                    @if ($range)
                                        {{ rtrim(rtrim(number_format((float) $range->min_value, 1, ',', '.'), '0'), ',') }}
                                        &ndash;
                                        {{ rtrim(rtrim(number_format((float) $range->max_value, 1, ',', '.'), '0'), ',') }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-gray-500">
            Angka di tabel ini adalah standar FitMate yang berlaku sama di seluruh toko.
        </p>
    </x-ui.modal>
@endif
