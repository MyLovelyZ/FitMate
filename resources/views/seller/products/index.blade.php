<x-layouts.dashboard title="Produk" area="seller">
    <x-ui.page-header title="Produk" subtitle="Kelola produk, varian, dan stok.">
        <x-slot:actions>
            <x-ui.button :href="route('seller.products.create')" size="sm">Tambah Produk</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    <form action="{{ route('seller.products.index') }}" method="GET" class="mb-4 flex max-w-xs items-center gap-2">
        <x-ui.select name="status" placeholder="Semua status" :options="$statusOptions" :selected="request('status')" x-on:change="$el.form.submit()" />
    </form>

    @if ($products->isEmpty())
        <x-ui.empty-state title="Belum ada produk" description="Tambahkan produk pertamamu, lengkap dengan ukurannya.">
            <x-ui.button :href="route('seller.products.create')" size="sm">Tambah Produk</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="py-2 pr-4">Produk</th>
                            <th class="py-2 pr-4">Kategori</th>
                            <th class="py-2 pr-4">Harga</th>
                            <th class="py-2 pr-4">Varian</th>
                            <th class="py-2 pr-4">Stok</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        @foreach ($products as $product)
                            <tr>
                                <td class="py-3 pr-4 font-medium">{{ $product->name }}</td>
                                <td class="py-3 pr-4 text-gray-500">{{ $product->category?->name }}</td>
                                <td class="py-3 pr-4">Rp{{ number_format($product->displayPrice(), 0, ',', '.') }}</td>
                                <td class="py-3 pr-4 text-gray-500">{{ $product->variants->count() }}</td>
                                <td class="py-3 pr-4">{{ $product->variants->sum('stock') }}</td>
                                <td class="py-3 pr-4">
                                    <x-ui.badge :variant="$product->status->value === 'active' ? 'success' : ($product->status->value === 'rejected' ? 'danger' : 'gray')">
                                        {{ $product->status->label() }}
                                    </x-ui.badge>
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('seller.products.edit', $product) }}" class="text-brand-600 hover:underline">Ubah</a>

                                        <form action="{{ route('seller.products.destroy', $product) }}" method="POST">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div class="mt-6">{{ $products->links() }}</div>
    @endif
</x-layouts.dashboard>
