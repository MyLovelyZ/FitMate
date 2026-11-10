{{--
    Isi formulir produk bersama Seller Center (`pages::seller-product-form`) dan
    admin (`admin/create_product`). Taruh di dalam tag
    `<form method="POST" enctype="multipart/form-data" novalidate>` + `@csrf` milik
    halamannya; validasinya di server (SellerProductRequest / AdminProductRequest).

    - `options`: hasil `Product::formOptions()`.
    - `rows`: varian yang sudah ada (`$product->variantFormRows()`); isian lama
      (`old('variants')`) didahulukan kalau validasi gagal.
    - `bag`: MessageBag error formulir ini.
    - `stores`: diisi admin untuk memilih toko pemilik produk.
--}}
@props([
    'product' => null,
    'images' => collect(),
    'options',
    'rows' => [],
    'bag',
    'stores' => null,
    'cancelUrl',
])

@php
    $isEditing = $product !== null;
    $oldRows = old('variants');
    $rows = is_array($oldRows) ? array_values($oldRows) : $rows;
    $rows = collect($rows === [] ? [['stock' => 0]] : $rows)
        ->values()
        ->map(fn (array $row, int $index): array => [
            'key' => 'row-'.$index,
            'id' => (string) ($row['id'] ?? ''),
            'size_id' => (string) ($row['size_id'] ?? ''),
            'color_id' => (string) ($row['color_id'] ?? ''),
            'sku' => (string) ($row['sku'] ?? ''),
            'price' => (string) ($row['price'] ?? ''),
            'stock' => (string) ($row['stock'] ?? '0'),
        ])
        ->all();
    $primaryImageId = (int) old('primary_image_id', $images->firstWhere('is_primary', true)?->id);
    $removedImageIds = array_map('intval', (array) old('remove_images', []));
    $field = fn (string $name): string => 'h-11 w-full rounded-[2px] border bg-bg px-3 text-[13px] outline-none transition-colors placeholder:text-muted focus:border-ink '.($bag->has($name) ? 'border-sale' : 'border-line');
@endphp

@if ($bag->any())
    <div class="mt-6 rounded-md border border-sale/30 bg-sale/5 px-[18px] py-3.5 text-[13px] text-sale" role="alert">
        <p class="font-semibold">Produk belum tersimpan. Periksa lagi isian berikut:</p>
        <ul class="mt-1.5 list-disc pl-5 text-[12.5px]">
            @foreach ($bag->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div
    x-data="{
        category: @js((string) old('category_id', $product?->category_id ?? '')),
        categories: @js((object) $options['categoryMap']),
        sizes: @js((object) $options['sizesByType']),
        rows: @js($rows),
        errors: @js((object) $bag->getMessages()),
        previews: [],
        submitting: false,
        get sized() { return this.categories[this.category]?.sized ?? false },
        get sizeOptions() { return this.sizes[this.categories[this.category]?.type] ?? [] },
        addRow() { this.rows.push({ key: 'new-' + Date.now() + '-' + Math.random(), id: '', size_id: '', color_id: '', sku: '', price: '', stock: '0' }) },
        removeRow(index) { this.rows.splice(index, 1) },
        rowError(index, field) { return (this.errors['variants.' + index + '.' + field] ?? [])[0] ?? '' },
    }"
    x-init="$el.closest('form')?.addEventListener('submit', () => submitting = true)"
    class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]"
>
    <div class="flex min-w-0 flex-col gap-4">
        {{-- Informasi produk --}}
        <section class="rounded-md border border-line bg-surface px-[clamp(16px,3vw,24px)] py-5">
            <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Informasi Produk</h2>

            <div class="mt-4 flex flex-col gap-4">
                @if ($stores !== null)
                    <div>
                        <label for="store_id" class="block text-[12px] font-semibold">Toko pemilik</label>
                        <select id="store_id" name="store_id" class="{{ $field('store_id') }} mt-1.5">
                            <option value="">Pilih toko</option>
                            @foreach ($stores as $store)
                                <option value="{{ $store->id }}" @selected((string) old('store_id') === (string) $store->id)>{{ $store->displayName() }} · {{ $store->statusLabel() }}</option>
                            @endforeach
                        </select>
                        @if ($bag->has('store_id'))
                            <p class="mt-1 text-[12px] text-sale">{{ $bag->first('store_id') }}</p>
                        @else
                            <p class="mt-1 text-[11.5px] text-muted">Produk hanya tampil di katalog kalau tokonya aktif dan profilnya lengkap.</p>
                        @endif
                    </div>
                @endif

                <div>
                    <label for="name" class="block text-[12px] font-semibold">Nama produk</label>
                    <input id="name" type="text" name="name" maxlength="120" value="{{ old('name', $product?->name) }}" placeholder="Mis. Kemeja Linen Lengan Pendek" class="{{ $field('name') }} mt-1.5">
                    @if ($bag->has('name')) <p class="mt-1 text-[12px] text-sale">{{ $bag->first('name') }}</p> @endif
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="category_id" class="block text-[12px] font-semibold">Kategori</label>
                        <select id="category_id" name="category_id" x-model="category" class="{{ $field('category_id') }} mt-1.5">
                            <option value="">Pilih kategori</option>
                            @foreach ($options['categoryTypes'] as $type)
                                <optgroup label="{{ $type->name }}">
                                    @foreach ($type->categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) old('category_id', $product?->category_id) === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @if ($bag->has('category_id')) <p class="mt-1 text-[12px] text-sale">{{ $bag->first('category_id') }}</p> @endif
                    </div>

                    <div>
                        <label for="gender" class="block text-[12px] font-semibold">Untuk</label>
                        <select id="gender" name="gender" class="{{ $field('gender') }} mt-1.5">
                            @foreach (['unisex' => 'Unisex (pria & wanita)', 'men' => 'Pria', 'women' => 'Wanita'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('gender', $product?->gender ?? 'unisex') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if ($bag->has('gender')) <p class="mt-1 text-[12px] text-sale">{{ $bag->first('gender') }}</p> @endif
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-[12px] font-semibold">Deskripsi</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="5"
                        maxlength="5000"
                        placeholder="Bahan, potongan, cara perawatan…"
                        @class(['mt-1.5 w-full rounded-[2px] border bg-bg px-3 py-2.5 text-[13px] outline-none transition-colors placeholder:text-muted focus:border-ink', 'border-sale' => $bag->has('description'), 'border-line' => ! $bag->has('description')])
                    >{{ old('description', $product?->description) }}</textarea>
                    @if ($bag->has('description')) <p class="mt-1 text-[12px] text-sale">{{ $bag->first('description') }}</p> @endif
                </div>
            </div>
        </section>

        {{-- Foto --}}
        <section class="rounded-md border border-line bg-surface px-[clamp(16px,3vw,24px)] py-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Foto Produk</h2>
                <p class="text-[11.5px] text-muted">JPG, PNG, atau WebP · maks. 2 MB · min. 300×300 px · maks. {{ App\Models\Product::MAX_IMAGES }} foto</p>
            </div>

            @if ($images->isNotEmpty())
                <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($images as $image)
                        <li wire:key="image-{{ $image->id }}" class="flex flex-col gap-2 rounded-[2px] border border-line p-2">
                            <img src="{{ $image->url }}" alt="{{ $image->alt_text }}" class="aspect-[4/5] w-full rounded-[2px] bg-cream object-cover">
                            <label class="flex cursor-pointer items-center gap-1.5 text-[12px]">
                                <input type="radio" name="primary_image_id" value="{{ $image->id }}" @checked($primaryImageId === $image->id)>
                                Foto utama
                            </label>
                            <label class="flex cursor-pointer items-center gap-1.5 text-[12px] text-sale">
                                <input type="checkbox" name="remove_images[]" value="{{ $image->id }}" @checked(in_array($image->id, $removedImageIds, true))>
                                Hapus
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-4">
                <label for="images" class="block text-[12px] font-semibold">{{ $isEditing ? 'Tambah foto' : 'Unggah foto' }}</label>
                <input
                    id="images"
                    type="file"
                    name="images[]"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    x-on:change="previews = Array.from($event.target.files).map((file) => ({ name: file.name, url: URL.createObjectURL(file) }))"
                    @class(['mt-1.5 block w-full rounded-[2px] border border-dashed bg-bg px-3 py-4 text-[12.5px] file:mr-3 file:cursor-pointer file:rounded-[2px] file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-[11px] file:font-bold file:uppercase file:tracking-[0.08em] file:text-cream', 'border-sale' => $bag->has('images') || $bag->has('images.*'), 'border-line' => ! $bag->has('images') && ! $bag->has('images.*')])
                >
                <p class="mt-1 text-[11.5px] text-muted">{{ $isEditing ? 'Foto baru ditambahkan setelah foto yang ada.' : 'Foto pertama otomatis jadi foto utama di katalog.' }} Kalau penyimpanan gagal, pilih ulang fotonya.</p>
                @if ($bag->has('images')) <p class="mt-1 text-[12px] text-sale">{{ $bag->first('images') }}</p> @endif
                @foreach ($bag->get('images.*') as $messages)
                    <p class="mt-1 text-[12px] text-sale">{{ $messages[0] }}</p>
                @endforeach

                {{-- Pratinjau langsung foto yang dipilih, sebelum dikirim ke server. --}}
                <ul x-show="previews.length > 0" x-cloak class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6" aria-label="Pratinjau foto">
                    <template x-for="preview in previews" x-bind:key="preview.url">
                        <li>
                            <img x-bind:src="preview.url" x-bind:alt="preview.name" class="aspect-[4/5] w-full rounded-[2px] border border-line object-cover">
                        </li>
                    </template>
                </ul>
            </div>
        </section>

        {{-- Varian & stok --}}
        <section class="rounded-md border border-line bg-surface">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-[18px] py-3">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Varian &amp; Stok</h2>
                <button type="button" x-on:click="addRow()" class="cursor-pointer rounded-[2px] border border-line px-3 py-1.5 text-[12px] font-semibold transition-colors hover:border-ink">+ Tambah Varian</button>
            </div>

            <p class="px-[18px] pt-3 text-[12px] text-muted" x-show="! sized" x-cloak>Kategori ini tidak memakai ukuran (atau kategori belum dipilih). Bedakan varian lewat warna.</p>
            @if ($bag->has('variants')) <p class="px-[18px] pt-3 text-[12px] text-sale">{{ $bag->first('variants') }}</p> @endif

            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-[12.5px]">
                    <thead class="text-[11px] uppercase tracking-[0.1em] text-muted">
                        <tr>
                            <th class="px-[18px] py-3 font-semibold" x-show="sized">Ukuran</th>
                            <th class="px-3 py-3 font-semibold">Warna</th>
                            <th class="px-3 py-3 font-semibold">SKU <span class="font-normal normal-case tracking-normal">(opsional)</span></th>
                            <th class="px-3 py-3 font-semibold">Harga khusus <span class="font-normal normal-case tracking-normal">(opsional)</span></th>
                            <th class="px-3 py-3 font-semibold">Stok</th>
                            <th class="px-3 py-3"><span class="sr-only">Hapus</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, index) in rows" x-bind:key="row.key">
                            <tr class="border-t border-line align-top">
                                <td class="px-[18px] py-2.5" x-show="sized">
                                    <select
                                        x-bind:name="`variants[${index}][size_id]`"
                                        x-bind:disabled="! sized"
                                        x-model="row.size_id"
                                        x-bind:aria-label="`Ukuran varian ${index + 1}`"
                                        class="h-10 w-full min-w-[90px] rounded-[2px] border border-line bg-bg px-2 text-[12.5px]"
                                    >
                                        <option value="">Pilih</option>
                                        <template x-for="size in sizeOptions" x-bind:key="size.id">
                                            <option x-bind:value="size.id" x-text="size.name" x-bind:selected="String(size.id) === String(row.size_id)"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-[11.5px] text-sale" x-show="rowError(index, 'size_id')" x-text="rowError(index, 'size_id')" x-cloak></p>
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="hidden" x-bind:name="`variants[${index}][id]`" x-bind:value="row.id">
                                    <select
                                        x-bind:name="`variants[${index}][color_id]`"
                                        x-model="row.color_id"
                                        x-bind:aria-label="`Warna varian ${index + 1}`"
                                        class="h-10 w-full min-w-[110px] rounded-[2px] border border-line bg-bg px-2 text-[12.5px]"
                                    >
                                        <option value="">Tanpa warna</option>
                                        @foreach ($options['colors'] as $color)
                                            <option value="{{ $color->id }}" x-bind:selected="String(row.color_id) === '{{ $color->id }}'">{{ $color->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-[11.5px] text-sale" x-show="rowError(index, 'color_id')" x-text="rowError(index, 'color_id')" x-cloak></p>
                                </td>
                                <td class="px-3 py-2.5">
                                    <input
                                        type="text"
                                        x-bind:name="`variants[${index}][sku]`"
                                        x-model="row.sku"
                                        maxlength="64"
                                        placeholder="Otomatis"
                                        x-bind:aria-label="`SKU varian ${index + 1}`"
                                        class="h-10 w-full min-w-[110px] rounded-[2px] border border-line bg-bg px-2 text-[12.5px] uppercase placeholder:normal-case placeholder:text-muted"
                                    >
                                    <p class="mt-1 text-[11.5px] text-sale" x-show="rowError(index, 'sku')" x-text="rowError(index, 'sku')" x-cloak></p>
                                </td>
                                <td class="px-3 py-2.5">
                                    <input
                                        type="number"
                                        min="1000"
                                        step="500"
                                        x-bind:name="`variants[${index}][price]`"
                                        x-model="row.price"
                                        placeholder="Ikut harga jual"
                                        x-bind:aria-label="`Harga varian ${index + 1}`"
                                        class="h-10 w-full min-w-[120px] rounded-[2px] border border-line bg-bg px-2 text-[12.5px] placeholder:text-muted"
                                    >
                                    <p class="mt-1 text-[11.5px] text-sale" x-show="rowError(index, 'price')" x-text="rowError(index, 'price')" x-cloak></p>
                                </td>
                                <td class="px-3 py-2.5">
                                    <input
                                        type="number"
                                        min="0"
                                        x-bind:name="`variants[${index}][stock]`"
                                        x-model="row.stock"
                                        x-bind:aria-label="`Stok varian ${index + 1}`"
                                        class="h-10 w-full min-w-[80px] rounded-[2px] border border-line bg-bg px-2 text-[12.5px]"
                                    >
                                    <p class="mt-1 text-[11.5px] text-sale" x-show="rowError(index, 'stock')" x-text="rowError(index, 'stock')" x-cloak></p>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <button
                                        type="button"
                                        x-on:click="removeRow(index)"
                                        x-bind:disabled="rows.length === 1"
                                        class="cursor-pointer px-2 py-2 text-[12px] font-semibold text-muted transition-colors hover:text-sale disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <aside class="flex flex-col gap-4 lg:sticky lg:top-[calc(var(--spacing-header)+16px)]">
        <section class="rounded-md border border-line bg-surface px-[18px] py-5">
            <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Harga</h2>

            <div class="mt-4 flex flex-col gap-4">
                <div>
                    <label for="base_price" class="block text-[12px] font-semibold">Harga jual (Rp)</label>
                    <input id="base_price" type="number" name="base_price" min="1000" step="500" value="{{ old('base_price', $product ? (int) $product->base_price : '') }}" placeholder="150000" class="{{ $field('base_price') }} mt-1.5">
                    @if ($bag->has('base_price')) <p class="mt-1 text-[12px] text-sale">{{ $bag->first('base_price') }}</p> @endif
                </div>

                <div>
                    <label for="compare_at_price" class="block text-[12px] font-semibold">Harga coret (Rp, opsional)</label>
                    <input id="compare_at_price" type="number" name="compare_at_price" min="1000" step="500" value="{{ old('compare_at_price', $product?->compare_at_price ? (int) $product->compare_at_price : '') }}" class="{{ $field('compare_at_price') }} mt-1.5">
                    @if ($bag->has('compare_at_price'))
                        <p class="mt-1 text-[12px] text-sale">{{ $bag->first('compare_at_price') }}</p>
                    @else
                        <p class="mt-1 text-[11.5px] text-muted">Isi lebih tinggi dari harga jual agar produk masuk menu Sale.</p>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-md border border-line bg-surface px-[18px] py-5">
            <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Status</h2>

            <input type="hidden" name="is_active" value="0">
            <label class="mt-3 flex cursor-pointer items-start gap-2.5 text-[13px]">
                <input type="checkbox" name="is_active" value="1" class="mt-0.5" @checked(old('is_active', $product?->is_active ?? true))>
                <span>
                    <span class="font-medium">Tayangkan di katalog</span>
                    <span class="mt-0.5 block text-[12px] text-muted">Matikan untuk menyimpan sebagai draf.</span>
                </span>
            </label>
        </section>

        <div class="flex flex-col gap-2">
            <button
                type="submit"
                x-bind:disabled="submitting"
                class="inline-flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-[2px] bg-ink px-5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85 disabled:cursor-wait disabled:opacity-60"
            >
                <span x-show="! submitting">{{ $isEditing ? 'Simpan Perubahan' : 'Simpan Produk' }}</span>
                <span x-show="submitting" x-cloak>Menyimpan…</span>
            </button>
            <a href="{{ $cancelUrl }}" wire:navigate class="inline-flex h-11 items-center justify-center rounded-[2px] border border-line text-[11.5px] font-bold uppercase tracking-[0.1em] transition-colors hover:border-ink">Batal</a>
        </div>
    </aside>
</div>
