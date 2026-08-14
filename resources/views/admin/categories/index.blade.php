<x-layouts.dashboard title="Kategori dan Brand" area="admin">
    <x-ui.page-header title="Kategori dan Brand" subtitle="Kategori menentukan size_type produk di bawahnya.">
        <x-slot:actions>
            <x-ui.button size="sm" variant="secondary" x-on:click="$dispatch('open-modal', 'brand-form')">Tambah Brand</x-ui.button>
            <x-ui.button size="sm" x-on:click="$dispatch('open-modal', 'category-form')">Tambah Kategori</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-4 lg:col-span-2">
            @if ($categories->isEmpty())
                <x-ui.empty-state title="Belum ada kategori" />
            @else
                @foreach ($categories as $category)
                    <x-ui.card>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ $category->name }}</span>
                                <x-ui.badge>{{ $category->type->label() }}</x-ui.badge>
                                <x-ui.badge :variant="$category->requiresSizing() ? 'brand' : 'gray'">{{ $category->size_type->label() }}</x-ui.badge>
                            </div>

                            <form action="{{ route('admin.categories.destroy', $category) }}" method="POST">
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="text-sm text-red-600 hover:underline">Hapus</button>
                            </form>
                        </div>

                        @if ($category->children->isNotEmpty())
                            <div class="mt-3 flex flex-col divide-y divide-gray-100">
                                @foreach ($category->children as $child)
                                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm first:pt-0 last:pb-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-400">&rdsh;</span>
                                            <span>{{ $child->name }}</span>
                                            <x-ui.badge :variant="$child->requiresSizing() ? 'brand' : 'gray'">{{ $child->size_type->label() }}</x-ui.badge>
                                        </div>

                                        <form action="{{ route('admin.categories.destroy', $child) }}" method="POST">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="text-xs text-red-600 hover:underline">Hapus</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </x-ui.card>
                @endforeach
            @endif
        </div>

        <x-ui.card heading="Brand" class="h-fit">
            @if ($brands->isEmpty())
                <p class="text-sm text-gray-500">Belum ada brand.</p>
            @else
                <div class="flex flex-col divide-y divide-gray-100">
                    @foreach ($brands as $brand)
                        <div class="flex items-center justify-between gap-2 py-2 text-sm first:pt-0 last:pb-0">
                            <span>{{ $brand->name }}</span>

                            <form action="{{ route('admin.brands.destroy', $brand) }}" method="POST">
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="text-xs text-red-600 hover:underline">Hapus</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>

    <x-ui.modal name="category-form" title="Tambah Kategori">
        <form action="{{ route('admin.categories.store') }}" method="POST" class="flex flex-col gap-4">
            @csrf

            <x-form.field name="name" label="Nama Kategori" required>
                <x-ui.input name="name" />
            </x-form.field>

            <x-form.field name="parent_id" label="Induk" hint="Kosongkan untuk kategori tingkat atas.">
                <x-ui.select name="parent_id" placeholder="Tanpa induk" :options="$parentOptions->all()" />
            </x-form.field>

            <x-form.field name="type" label="Tipe" required>
                <x-ui.select name="type" :options="$typeOptions" />
            </x-form.field>

            <x-form.field name="size_type" label="Jenis Ukuran" required hint="Pilih Tanpa Ukuran untuk aksesoris — produknya otomatis dilewati mesin rekomendasi.">
                <x-ui.select name="size_type" :options="$sizeTypeOptions" />
            </x-form.field>

            <x-form.field name="sort_order" label="Urutan">
                <x-ui.input name="sort_order" type="number" min="0" value="1" />
            </x-form.field>

            <x-ui.button type="submit">Simpan Kategori</x-ui.button>
        </form>
    </x-ui.modal>

    <x-ui.modal name="brand-form" title="Tambah Brand">
        <form action="{{ route('admin.brands.store') }}" method="POST" class="flex flex-col gap-4">
            @csrf

            <x-form.field name="name" label="Nama Brand" required>
                <x-ui.input name="name" />
            </x-form.field>

            <x-form.field name="description" label="Keterangan">
                <x-ui.textarea name="description" rows="2" />
            </x-form.field>

            <x-ui.button type="submit">Simpan Brand</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.dashboard>
