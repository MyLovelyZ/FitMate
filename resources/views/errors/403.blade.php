<x-layouts.app title="Akses Ditolak">
    <div class="mx-auto flex max-w-md flex-col items-center gap-3 py-20 text-center">
        <p class="text-5xl font-bold text-brand-600">403</p>
        <h1 class="text-xl font-semibold">Akses ditolak</h1>
        <p class="text-sm text-gray-500">Akunmu tidak punya izin untuk membuka halaman ini.</p>

        <x-ui.button :href="route('home')" class="mt-2">Kembali ke Beranda</x-ui.button>
    </div>
</x-layouts.app>
