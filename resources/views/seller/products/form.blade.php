<x-layouts.dashboard :title="$product ? 'Ubah Produk' : 'Tambah Produk'" area="seller">
    @php
        // Peta kategori -> jenis ukuran dan chart -> entry-nya diserahkan ke
        // Alpine, supaya seller hanya melihat pilihan ukuran yang sah untuk
        // kategori yang dia pilih. Validasi sebenarnya tetap di server
        // (BE-034) — form hanya membantu, bukan penjaga.
        $categoryMap = $categories->mapWithKeys(fn ($category) => [
            $category->id => $category->size_type->value,
        ]);

        $chartMap = $sizeCharts->mapWithKeys(fn ($chart) => [
            $chart->id => [
                'sizeType' => $chart->size_type->value,
                'name' => $chart->name,
                'entries' => $chart->entries->map(fn ($entry) => ['id' => $entry->id, 'label' => $entry->label])->values(),
            ],
        ]);

        $existingVariants = $product
            ? $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'size_chart_entry_id' => $variant->size_chart_entry_id,
                'sku' => $variant->sku,
                'color_name' => $variant->color_name,
                'color_hex' => $variant->color_hex,
                'price' => (float) $variant->price,
                'stock' => $variant->stock,
                'is_active' => $variant->is_active,
            ])->values()
            : collect();
    @endphp

    <x-ui.page-header
        :title="$product ? 'Ubah Produk' : 'Tambah Produk'"
        subtitle="Ukuran produk wajib mengacu ke standar FitMate."
    />

    <form
        action="{{ $product ? route('seller.products.update', $product) : route('seller.products.store') }}"
        method="POST"
        enctype="multipart/form-data"
        class="flex max-w-3xl flex-col gap-4"
        x-data="{
            categoryMap: {{ Js::from($categoryMap) }},
            chartMap: {{ Js::from($chartMap) }},
            categoryId: {{ Js::from(old('category_id', $product?->category_id)) }},
            sizeChartId: {{ Js::from(old('size_chart_id', $product?->size_chart_id)) }},
            variants: {{ Js::from($existingVariants->isEmpty() ? [['id' => null, 'size_chart_entry_id' => null, 'sku' => '', 'color_name' => '', 'color_hex' => '', 'price' => '', 'stock' => 0, 'is_active' => true]] : $existingVariants) }},
            get sizeType() {
                return this.categoryMap[this.categoryId] ?? null;
            },
            get availableCharts() {
                return Object.entries(this.chartMap).filter(([, chart]) => chart.sizeType === this.sizeType);
            },
            get entries() {
                return this.chartMap[this.sizeChartId]?.entries ?? [];
            },
            get requiresSizing() {
                return this.sizeType !== null && this.sizeType !== 'none';
            },
            addVariant() {
                this.variants.push({ id: null, size_chart_entry_id: null, sku: '', color_name: '', color_hex: '', price: '', stock: 0, is_active: true });
            },
            removeVariant(index) {
                if (this.variants.length > 1) {
                    this.variants.splice(index, 1);
                }
            },
        }"
        x-effect="if (! availableCharts.some(([id]) => Number(id) === Number(sizeChartId))) { sizeChartId = null }"
    >
        @csrf
        @if ($product)
            @method('PATCH')
        @endif

        <x-ui.card heading="Informasi Dasar">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field name="name" label="Nama Produk" required class="sm:col-span-2">
                    <x-ui.input name="name" :value="$product?->name" />
                </x-form.field>

                <x-form.field name="category_id" label="Kategori" required hint="Kategori menentukan jenis ukuran: atasan, bawahan, atau alas kaki.">
                    <select name="category_id" id="category_id" x-model.number="categoryId" class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-gray-300 ring-inset focus:ring-2 focus:ring-brand-600 focus:outline-none">
                        <option value="">Pilih kategori</option>

                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">
                                {{ $category->parent ? $category->parent->name.' / ' : '' }}{{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field name="brand_id" label="Brand">
                    <x-ui.select name="brand_id" placeholder="Pilih brand" :options="$brands->all()" :selected="$product?->brand_id" />
                </x-form.field>

                <x-form.field name="target_gender" label="Untuk" required>
                    <x-ui.select name="target_gender" :options="$genderOptions" :selected="$product?->target_gender?->value ?? 'unisex'" />
                </x-form.field>

                <x-form.field name="status" label="Status" required hint="Pilih Menunggu Review agar admin memeriksanya sebelum tayang.">
                    <x-ui.select name="status" :options="$statusOptions" :selected="$product?->status?->value ?? 'draft'" />
                </x-form.field>

                <x-form.field name="base_price" label="Harga Tampilan (Rp)" required>
                    <x-ui.input name="base_price" type="number" min="0" step="1000" :value="$product?->base_price" />
                </x-form.field>

                <x-form.field name="weight_gram" label="Berat (gram)" required>
                    <x-ui.input name="weight_gram" type="number" min="1" :value="$product?->weight_gram ?? 500" />
                </x-form.field>

                <x-form.field name="description" label="Deskripsi" class="sm:col-span-2">
                    <x-ui.textarea name="description" :value="$product?->description" />
                </x-form.field>
            </div>
        </x-ui.card>

        <x-ui.card heading="Tabel Ukuran">
            <div x-show="requiresSizing" x-cloak>
                <x-form.field name="size_chart_id" label="Standar Ukuran FitMate" required hint="Hanya tabel yang jenisnya cocok dengan kategori di atas yang bisa dipilih.">
                    <select name="size_chart_id" id="size_chart_id" x-model.number="sizeChartId" class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-gray-300 ring-inset focus:ring-2 focus:ring-brand-600 focus:outline-none">
                        <option value="">Pilih tabel ukuran</option>

                        <template x-for="[id, chart] in availableCharts" x-bind:key="id">
                            <option x-bind:value="id" x-text="chart.name" x-bind:selected="Number(id) === Number(sizeChartId)"></option>
                        </template>
                    </select>
                </x-form.field>
            </div>

            <p x-show="! requiresSizing" x-cloak class="text-sm text-gray-500">
                Kategori ini tidak memakai ukuran badan, jadi produknya tidak perlu tabel ukuran.
            </p>
        </x-ui.card>

        {{--
            BE-034: `size_chart_entry_id` tiap varian harus milik chart produk ini,
            dan jenis chart harus sama dengan jenis ukuran kategorinya. Keduanya
            divalidasi ulang di StoreProductRequest — daftar di bawah hanya
            mempersempit pilihan, bukan menjamin.
        --}}
        <x-ui.card heading="Varian dan Ukuran">
            <div class="flex flex-col gap-3">
                <template x-for="(variant, index) in variants" x-bind:key="index">
                    <div class="grid gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-6">
                        <input type="hidden" x-bind:name="`variants[${index}][id]`" x-bind:value="variant.id">

                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium text-gray-600">Ukuran</label>

                            <select
                                x-bind:name="`variants[${index}][size_chart_entry_id]`"
                                x-model.number="variant.size_chart_entry_id"
                                x-bind:disabled="! requiresSizing"
                                class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-gray-300 ring-inset disabled:bg-gray-50"
                            >
                                <option value="">Tanpa ukuran</option>

                                <template x-for="entry in entries" x-bind:key="entry.id">
                                    <option x-bind:value="entry.id" x-text="entry.label"></option>
                                </template>
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium text-gray-600">SKU</label>
                            <input type="text" x-bind:name="`variants[${index}][sku]`" x-model="variant.sku" class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-gray-300 ring-inset">
                        </div>

                        <div>
                            <label class="text-xs font-medium text-gray-600">Warna</label>
                            <input type="text" x-bind:name="`variants[${index}][color_name]`" x-model="variant.color_name" class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-gray-300 ring-inset">
                        </div>

                        <div>
                            <label class="text-xs font-medium text-gray-600">Kode Warna</label>
                            <input type="text" x-bind:name="`variants[${index}][color_hex]`" x-model="variant.color_hex" placeholder="#111827" maxlength="7" class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-gray-300 ring-inset">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium text-gray-600">Harga (Rp)</label>
                            <input type="number" min="0" step="1000" x-bind:name="`variants[${index}][price]`" x-model="variant.price" class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-gray-300 ring-inset">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium text-gray-600">Stok</label>
                            <input type="number" min="0" x-bind:name="`variants[${index}][stock]`" x-model="variant.stock" class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-gray-300 ring-inset">
                        </div>

                        <div class="flex items-end justify-between gap-2 sm:col-span-2">
                            <label class="flex items-center gap-2 text-xs text-gray-600">
                                <input type="checkbox" x-bind:name="`variants[${index}][is_active]`" value="1" x-model="variant.is_active" class="rounded border-gray-300 text-brand-600">
                                Aktif
                            </label>

                            <button type="button" class="text-xs text-red-600 hover:underline" x-on:click="removeVariant(index)">Hapus</button>
                        </div>
                    </div>
                </template>

                <x-ui.button type="button" variant="secondary" size="sm" class="self-start" x-on:click="addVariant()">Tambah Varian</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card heading="Gambar Produk">
            <input type="file" name="images[]" accept="image/*" multiple class="text-sm">
            <p class="mt-2 text-xs text-gray-500">Maksimal 8 gambar, masing-masing 2 MB. Gambar pertama jadi gambar utama.</p>

            @if ($product && $product->images->isNotEmpty())
                <div class="mt-4 grid grid-cols-4 gap-3">
                    @foreach ($product->images as $image)
                        <div class="aspect-square overflow-hidden rounded-lg bg-gray-100">
                            <img src="{{ $image->url() }}" alt="{{ $image->alt_text }}" class="h-full w-full object-cover">
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.button type="submit" class="self-start">Simpan Produk</x-ui.button>
    </form>
</x-layouts.dashboard>
