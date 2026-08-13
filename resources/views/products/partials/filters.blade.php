{{-- TODO(BE-060): isi opsi filter dari database (kategori, brand, ukuran, warna). --}}
<form action="{{ route('products.index') }}" method="GET" x-data="{ open: false }">
    <x-ui.card>
        <div class="flex items-center justify-between lg:hidden">
            <span class="font-medium">Filter</span>

            <x-ui.button type="button" variant="ghost" size="sm" x-on:click="open = ! open">Ubah</x-ui.button>
        </div>

        <div class="flex-col gap-5 lg:flex" x-bind:class="open ? 'flex' : 'hidden'">
            <x-form.field name="category" label="Kategori">
                <x-ui.select name="category" placeholder="Semua kategori" :options="[]" />
            </x-form.field>

            <x-form.field name="brand" label="Brand">
                <x-ui.select name="brand" placeholder="Semua brand" :options="[]" />
            </x-form.field>

            <div class="grid grid-cols-2 gap-2">
                <x-form.field name="min_price" label="Harga Min">
                    <x-ui.input name="min_price" type="number" min="0" />
                </x-form.field>

                <x-form.field name="max_price" label="Harga Max">
                    <x-ui.input name="max_price" type="number" min="0" />
                </x-form.field>
            </div>

            <div class="flex gap-2">
                <x-ui.button type="submit" size="sm" class="flex-1">Terapkan</x-ui.button>
                <x-ui.button :href="route('products.index')" variant="secondary" size="sm">Reset</x-ui.button>
            </div>
        </div>
    </x-ui.card>
</form>
