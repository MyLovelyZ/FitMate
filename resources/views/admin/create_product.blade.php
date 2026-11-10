{{--
    Form "Tambah Produk" admin (AdminController@createProduct). Dikirim ke
    AdminController@storeProduct (POST /admin/products) sebagai multipart/form-data
    karena membawa file foto. `novalidate`: aturan isian ditegakkan di server.
--}}
<x-layouts::admin title="Tambah Produk">
    <div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
        <x-ui.area-header
            :breadcrumbs="['Beranda' => route('home'), 'Admin' => route('admin.dashboard'), 'Produk' => route('admin.products.index'), 'Tambah Produk' => null]"
            title="Tambah Produk"
            description="Unggah produk baru atas nama salah satu toko. Produk langsung tampil di katalog kalau tokonya aktif."
            :links="auth()->user()->adminAreaLinks()"
        />

        <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" novalidate>
            @csrf

            <x-product.fields
                :options="$options"
                :bag="$errors->getBag('default')"
                :stores="$stores"
                :cancel-url="route('admin.products.index')"
            />
        </form>
    </div>
</x-layouts::admin>
