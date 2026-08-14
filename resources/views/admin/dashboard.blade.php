<x-layouts.dashboard title="Ringkasan Admin" area="admin">
    <x-ui.page-header title="Ringkasan Admin" subtitle="Kondisi platform secara keseluruhan." />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.card>
            <p class="text-sm text-gray-500">Toko Menunggu Verifikasi</p>
            <p class="mt-1 text-2xl font-semibold">{{ $pendingStoreCount }}</p>
            <a href="{{ route('admin.moderation.index') }}" class="text-xs text-brand-600 hover:underline">Tinjau</a>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500">Produk Menunggu Review</p>
            <p class="mt-1 text-2xl font-semibold">{{ $pendingProductCount }}</p>
            <a href="{{ route('admin.moderation.index') }}" class="text-xs text-brand-600 hover:underline">Tinjau</a>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500">Pesanan Hari Ini</p>
            <p class="mt-1 text-2xl font-semibold">{{ $todayOrderCount }}</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500">Keluhan Ukuran</p>
            <p class="mt-1 text-2xl font-semibold">{{ $sizeComplaintCount }}</p>
            <p class="text-xs text-gray-400">Laporan kekecilan atau kebesaran</p>
        </x-ui.card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{--
            BE-082. Kalau satu arah menumpuk, itu bukti untuk menyetel ulang
            rentang di tabel standar — bukan tebakan.
        --}}
        <x-ui.card heading="Umpan Balik Ukuran">
            @php($totalFeedback = array_sum($fitFeedbackBreakdown))

            @if ($totalFeedback === 0)
                <p class="text-sm text-gray-500">Belum ada umpan balik ukuran dari pembeli.</p>
            @else
                <div class="flex flex-col gap-3">
                    @foreach ($fitFeedbackBreakdown as $label => $count)
                        <div class="flex flex-col gap-1">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">{{ $label }}</span>
                                <span class="font-medium">{{ $count }}</span>
                            </div>

                            <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-brand-600" style="width: {{ $totalFeedback > 0 ? round($count / $totalFeedback * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 text-xs text-gray-500">
                    Kalau "Kekecilan" atau "Kebesaran" menumpuk pada satu kategori, tinjau rentangnya di
                    <a href="{{ route('admin.size-charts.index') }}" class="text-brand-600 hover:underline">Standar Ukuran</a>.
                </p>
            @endif
        </x-ui.card>

        {{-- BE-083: metrik yang membuktikan fitur intinya berhasil atau tidak. --}}
        <x-ui.card heading="Kepatuhan Rekomendasi">
            @if ($recommendationCompliance['total'] === 0)
                <p class="text-sm text-gray-500">Belum ada pembelian yang bisa dibandingkan dengan rekomendasi.</p>
            @else
                <p class="text-3xl font-semibold">{{ $recommendationCompliance['percentage'] }}%</p>

                <p class="mt-1 text-sm text-gray-500">
                    {{ $recommendationCompliance['followed'] }} dari {{ $recommendationCompliance['total'] }} pembelian
                    memakai ukuran yang disarankan FitMate.
                </p>

                <p class="mt-4 text-xs text-gray-500">
                    Angka rendah berarti pembeli tidak percaya sarannya, atau ukuran yang disarankan sering habis stok.
                    Keduanya perlu ditelusuri.
                </p>
            @endif
        </x-ui.card>
    </div>
</x-layouts.dashboard>
