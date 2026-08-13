<x-layouts.dashboard title="Form Produk" area="seller">
    <x-ui.page-header title="Tambah Produk" subtitle="Ukuran produk wajib mengacu ke standar FitMate." />

    <form action="#" method="POST" class="flex max-w-3xl flex-col gap-4">
        @csrf

        <x-ui.card heading="Informasi Dasar">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field name="name" label="Nama Produk" required class="sm:col-span-2">
                    <x-ui.input name="name" />
                </x-form.field>

                <x-form.field name="category_id" label="Kategori" required hint="Kategori menentukan jenis ukuran: atasan, bawahan, atau alas kaki.">
                    <x-ui.select name="category_id" placeholder="Pilih kategori" :options="[]" />
                </x-form.field>

                <x-form.field name="brand_id" label="Brand">
                    <x-ui.select name="brand_id" placeholder="Pilih brand" :options="[]" />
                </x-form.field>

                <x-form.field name="description" label="Deskripsi" class="sm:col-span-2">
                    <x-ui.textarea name="description" />
                </x-form.field>
            </div>
        </x-ui.card>

        {{--
            TODO(BE-034): size_chart_entry_id tiap varian WAJIB milik chart produk ini,
            dan size_type chart harus sama dengan size_type kategori.
            Validasi di server, jangan hanya mengandalkan form.
        --}}
        <x-ui.card heading="Varian dan Ukuran">
            <p class="text-sm text-gray-500">Baris varian ditambah dinamis dengan Alpine.</p>
        </x-ui.card>

        <x-ui.button type="submit" class="self-start">Simpan Produk</x-ui.button>
    </form>
</x-layouts.dashboard>
