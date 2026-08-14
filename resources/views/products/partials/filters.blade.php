{{-- Opsi filter diisi controller dari database (BE-060). --}}
<form action="{{ route('products.index') }}" method="GET" x-data="{ open: false }">
    @if (request()->filled('q'))
        <input type="hidden" name="q" value="{{ request('q') }}">
    @endif

    <x-ui.card>
        <div class="flex items-center justify-between lg:hidden">
            <span class="font-medium">Filter</span>

            <x-ui.button type="button" variant="ghost" size="sm" x-on:click="open = ! open">Ubah</x-ui.button>
        </div>

        <div class="flex-col gap-5 lg:flex" x-bind:class="open ? 'flex' : 'hidden'">
            <x-form.field name="category" label="Kategori">
                <x-ui.select name="category" placeholder="Semua kategori" :options="$categories->all()" :selected="request('category')" />
            </x-form.field>

            <x-form.field name="brand" label="Brand">
                <x-ui.select name="brand" placeholder="Semua brand" :options="$brands->all()" :selected="request('brand')" />
            </x-form.field>

            <x-form.field name="gender" label="Untuk">
                <x-ui.select name="gender" placeholder="Semua" :options="$genderOptions" :selected="request('gender')" />
            </x-form.field>

            <div class="grid grid-cols-2 gap-2">
                <x-form.field name="min_price" label="Harga Min">
                    <x-ui.input name="min_price" type="number" min="0" :value="request('min_price')" />
                </x-form.field>

                <x-form.field name="max_price" label="Harga Max">
                    <x-ui.input name="max_price" type="number" min="0" :value="request('max_price')" />
                </x-form.field>
            </div>

            <x-form.field name="size" label="Ukuran" hint="Label ukuran standar FitMate, misal M atau 42.">
                <x-ui.input name="size" :value="request('size')" />
            </x-form.field>

            <x-form.field name="min_rating" label="Rating Minimal">
                <x-ui.select
                    name="min_rating"
                    placeholder="Semua rating"
                    :options="['4' => '4 ke atas', '3' => '3 ke atas', '2' => '2 ke atas']"
                    :selected="request('min_rating')"
                />
            </x-form.field>

            <div class="flex gap-2">
                <x-ui.button type="submit" size="sm" class="flex-1">Terapkan</x-ui.button>
                <x-ui.button :href="route('products.index')" variant="secondary" size="sm">Reset</x-ui.button>
            </div>
        </div>
    </x-ui.card>
</form>
