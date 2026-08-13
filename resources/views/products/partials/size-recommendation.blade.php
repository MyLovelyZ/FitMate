{{--
    Layar utama FitMate (BE-062).
    Data berasal dari SizeRecommendationService: entry yang disarankan, fit_score, fit_status.
    Wajib menangani: user belum login, belum isi ukuran, dimensi tidak lengkap, produk tanpa ukuran.
--}}
<x-ui.card class="border-brand-200 bg-brand-50">
    @guest
        <div class="flex flex-col items-start gap-3">
            <p class="text-sm text-gray-700">Masuk dan isi ukuran badanmu untuk melihat ukuran yang pas.</p>
            <x-ui.button :href="route('login')" size="sm">Masuk</x-ui.button>
        </div>
    @endguest

    @auth
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <p class="text-sm text-gray-600">Ukuran yang disarankan untukmu</p>
                    <p class="text-2xl font-bold text-brand-700">&mdash;</p>
                </div>

                <x-product.fit-badge status="" />
            </div>

            {{-- TODO(BE-031): tampilkan fit_score dan alasan per dimensi (dada, pinggang, dst). --}}
            <div class="flex flex-wrap gap-2">
                <x-ui.button size="sm" variant="secondary" x-on:click="$dispatch('open-modal', 'size-chart')">Lihat Tabel Ukuran</x-ui.button>
                <x-ui.button size="sm" variant="ghost" :href="route('profile.body')">Perbarui Ukuran Badan</x-ui.button>
            </div>
        </div>
    @endauth
</x-ui.card>

<x-ui.modal name="size-chart" title="Tabel Ukuran Standar FitMate" max-width="max-w-3xl">
    {{-- TODO(BE-037): render size_chart_entries + rentang min/max tiap dimensi. --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="py-2 pr-4">Ukuran</th>
                    <th class="py-2 pr-4">Dada (cm)</th>
                    <th class="py-2">Panjang (cm)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr>
                    <td class="py-2 pr-4 font-medium">M</td>
                    <td class="py-2 pr-4">&mdash;</td>
                    <td class="py-2">&mdash;</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-ui.modal>
