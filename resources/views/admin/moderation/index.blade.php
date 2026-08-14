<x-layouts.dashboard title="Verifikasi" area="admin">
    <x-ui.page-header title="Verifikasi" subtitle="Toko baru dan produk yang menunggu persetujuan." />

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="flex flex-col gap-4">
            <h2 class="text-sm font-semibold text-gray-900 uppercase">Toko Menunggu Verifikasi</h2>

            @forelse ($pendingStores as $store)
                <x-ui.card>
                    <div class="flex flex-col gap-1">
                        <span class="font-medium">{{ $store->name }}</span>
                        <span class="text-sm text-gray-500">{{ $store->owner?->name }} &middot; {{ $store->owner?->email }}</span>
                        <span class="text-sm text-gray-500">{{ $store->city }}, {{ $store->state }}</span>
                    </div>

                    <form action="{{ route('admin.moderation.stores', $store) }}" method="POST" class="mt-3 flex items-center gap-2">
                        @csrf
                        @method('PATCH')

                        <x-ui.select name="status" :options="['active' => 'Setujui', 'rejected' => 'Tolak', 'suspended' => 'Tangguhkan']" class="w-40" />
                        <x-ui.button type="submit" size="sm">Simpan</x-ui.button>
                    </form>
                </x-ui.card>
            @empty
                <x-ui.empty-state title="Tidak ada toko yang menunggu" />
            @endforelse
        </div>

        <div class="flex flex-col gap-4">
            <h2 class="text-sm font-semibold text-gray-900 uppercase">Produk Menunggu Review</h2>

            @forelse ($pendingProducts as $product)
                <x-ui.card>
                    <div class="flex flex-col gap-1">
                        <span class="font-medium">{{ $product->name }}</span>
                        <span class="text-sm text-gray-500">{{ $product->store?->name }} &middot; {{ $product->category?->name }}</span>

                        <div class="mt-1 flex flex-wrap gap-2">
                            <x-ui.badge>{{ $product->variants->count() }} varian</x-ui.badge>

                            {{--
                                Periksa rantai ukurannya: chart produk harus sejenis dengan
                                kategori. Ketidakcocokan seharusnya sudah ditolak validasi,
                                tapi tetap ditampilkan supaya data lama tidak lolos diam-diam.
                            --}}
                            @if ($product->sizeChart)
                                <x-ui.badge :variant="$product->sizeChart->size_type === $product->category?->size_type ? 'success' : 'danger'">
                                    {{ $product->sizeChart->name }}
                                </x-ui.badge>
                            @elseif ($product->category?->requiresSizing())
                                <x-ui.badge variant="danger">Kategori butuh ukuran tapi chart kosong</x-ui.badge>
                            @else
                                <x-ui.badge>Tanpa ukuran</x-ui.badge>
                            @endif
                        </div>
                    </div>

                    <form action="{{ route('admin.moderation.products', $product) }}" method="POST" class="mt-3 flex items-center gap-2">
                        @csrf
                        @method('PATCH')

                        <x-ui.select name="decision" :options="['approve' => 'Setujui', 'reject' => 'Tolak']" class="w-40" />
                        <x-ui.button type="submit" size="sm">Simpan</x-ui.button>
                    </form>
                </x-ui.card>
            @empty
                <x-ui.empty-state title="Tidak ada produk yang menunggu" />
            @endforelse
        </div>
    </div>
</x-layouts.dashboard>
