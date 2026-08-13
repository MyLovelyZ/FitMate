<nav class="sticky top-0 z-40 border-b border-gray-200 bg-white" x-data="{ mobileOpen: false }">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="text-xl font-bold tracking-tight text-brand-600">FitMate</a>

        <div class="hidden items-center gap-1 md:flex">
            <x-ui.nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">Katalog</x-ui.nav-link>
            <x-ui.nav-link :href="route('wishlist.index')" :active="request()->routeIs('wishlist.*')">Wishlist</x-ui.nav-link>
        </div>

        <form action="{{ route('products.index') }}" method="GET" class="ml-auto hidden max-w-sm flex-1 lg:block">
            <x-ui.input name="q" type="search" placeholder="Cari produk..." :value="request('q')" />
        </form>

        <div class="ml-auto flex items-center gap-2 lg:ml-0">
            <a href="{{ route('cart.index') }}" class="relative rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">
                Keranjang
                {{-- TODO(BE-064): ganti angka statis dengan jumlah item keranjang asli. --}}
                <span class="absolute -top-0.5 right-0 rounded-full bg-brand-600 px-1.5 text-xs font-semibold text-white">0</span>
            </a>

            @auth
                <x-ui.dropdown>
                    <x-slot:trigger>
                        <button type="button" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                            {{ auth()->user()->name }}
                        </button>
                    </x-slot>

                    <x-ui.dropdown-item :href="route('profile.edit')">Profil Saya</x-ui.dropdown-item>
                    <x-ui.dropdown-item :href="route('profile.body')">Ukuran Badan</x-ui.dropdown-item>
                    <x-ui.dropdown-item :href="route('orders.index')">Pesanan Saya</x-ui.dropdown-item>
                    <x-ui.dropdown-item :href="route('seller.dashboard')">Dashboard Toko</x-ui.dropdown-item>

                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-gray-50">Keluar</button>
                    </form>
                </x-ui.dropdown>
            @endauth

            @guest
                <a href="{{ route('login') }}" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 sm:block">Masuk</a>
                <x-ui.button :href="route('register')" size="sm">Daftar</x-ui.button>
            @endguest

            <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 md:hidden" x-on:click="mobileOpen = ! mobileOpen">
                <span class="sr-only">Buka menu</span>
                &#9776;
            </button>
        </div>
    </div>

    <div class="border-t border-gray-200 px-4 py-3 md:hidden" x-show="mobileOpen" x-cloak x-transition>
        <div class="flex flex-col gap-1">
            <x-ui.nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">Katalog</x-ui.nav-link>
            <x-ui.nav-link :href="route('wishlist.index')" :active="request()->routeIs('wishlist.*')">Wishlist</x-ui.nav-link>
            <x-ui.nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')">Pesanan Saya</x-ui.nav-link>
        </div>
    </div>
</nav>
