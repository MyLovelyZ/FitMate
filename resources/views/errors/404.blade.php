<x-layouts.app title="Halaman Tidak Ditemukan">
    <div class="mx-auto flex max-w-md flex-col items-center gap-3 py-20 text-center">
        <p class="text-5xl font-bold text-brand-600">404</p>
        <h1 class="text-xl font-semibold">Halaman tidak ditemukan</h1>
        <p class="text-sm text-gray-500">Alamat yang kamu tuju sudah dipindah atau tidak pernah ada.</p>

        <x-ui.button :href="route('home')" class="mt-2">Kembali ke Beranda</x-ui.button>
    </div>
</x-layouts.app>
