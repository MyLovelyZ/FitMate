<x-layouts.app title="Terjadi Kesalahan">
    <div class="mx-auto flex max-w-md flex-col items-center gap-3 py-20 text-center">
        <p class="text-5xl font-bold text-brand-600">500</p>
        <h1 class="text-xl font-semibold">Terjadi kesalahan di server</h1>
        <p class="text-sm text-gray-500">Kami sudah mencatat masalahnya. Coba lagi beberapa saat lagi.</p>

        <x-ui.button :href="route('home')" class="mt-2">Kembali ke Beranda</x-ui.button>
    </div>
</x-layouts.app>
