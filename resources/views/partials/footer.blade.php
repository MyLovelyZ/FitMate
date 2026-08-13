<footer class="border-t border-gray-200 bg-white">
    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-10 sm:px-6 lg:flex-row lg:items-start lg:justify-between lg:px-8">
        <div class="max-w-sm">
            <p class="text-lg font-bold text-brand-600">FitMate</p>
            <p class="mt-2 text-sm text-gray-500">Belanja pakaian dengan ukuran yang benar-benar pas, berdasarkan ukuran badanmu sendiri.</p>
        </div>

        <div class="grid grid-cols-2 gap-8 text-sm sm:grid-cols-3">
            <div class="flex flex-col gap-2">
                <p class="font-semibold text-gray-900">Belanja</p>
                <a href="{{ route('products.index') }}" class="text-gray-500 hover:text-brand-600">Katalog</a>
                <a href="{{ route('wishlist.index') }}" class="text-gray-500 hover:text-brand-600">Wishlist</a>
            </div>

            <div class="flex flex-col gap-2">
                <p class="font-semibold text-gray-900">Akun</p>
                <a href="{{ route('profile.body') }}" class="text-gray-500 hover:text-brand-600">Ukuran Badan</a>
                <a href="{{ route('orders.index') }}" class="text-gray-500 hover:text-brand-600">Pesanan</a>
            </div>

            <div class="flex flex-col gap-2">
                <p class="font-semibold text-gray-900">Penjual</p>
                <a href="{{ route('seller.dashboard') }}" class="text-gray-500 hover:text-brand-600">Dashboard Toko</a>
            </div>
        </div>
    </div>

    <div class="border-t border-gray-100 px-4 py-4 text-center text-xs text-gray-400">
        &copy; {{ now()->year }} FitMate.
    </div>
</footer>
