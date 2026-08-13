@php
    /** @var string $area Menentukan daftar menu yang ditampilkan: 'seller' atau 'admin'. */
    $area = $area ?? 'seller';
@endphp

<div class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden" x-show="sidebarOpen" x-cloak x-transition.opacity x-on:click="sidebarOpen = false"></div>

<aside
    class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-gray-200 bg-white transition-transform lg:translate-x-0"
    x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    <div class="flex h-16 items-center gap-2 border-b border-gray-200 px-6">
        <a href="{{ route('home') }}" class="text-xl font-bold tracking-tight text-brand-600">FitMate</a>
        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ $area === 'admin' ? 'Admin' : 'Penjual' }}</span>
    </div>

    <nav class="flex flex-1 flex-col gap-1 overflow-y-auto p-4">
        @if ($area === 'admin')
            <x-ui.nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">Ringkasan</x-ui.nav-link>
            <x-ui.nav-link :href="route('admin.size-charts.index')" :active="request()->routeIs('admin.size-charts.*')">Standar Ukuran</x-ui.nav-link>
            <x-ui.nav-link :href="route('admin.categories.index')" :active="request()->routeIs('admin.categories.*')">Kategori &amp; Brand</x-ui.nav-link>
        @else
            <x-ui.nav-link :href="route('seller.dashboard')" :active="request()->routeIs('seller.dashboard')">Ringkasan</x-ui.nav-link>
            <x-ui.nav-link :href="route('seller.products.index')" :active="request()->routeIs('seller.products.*')">Produk</x-ui.nav-link>
            <x-ui.nav-link :href="route('seller.orders.index')" :active="request()->routeIs('seller.orders.*')">Pesanan</x-ui.nav-link>
            <x-ui.nav-link :href="route('seller.store.edit')" :active="request()->routeIs('seller.store.*')">Profil Toko</x-ui.nav-link>
        @endif
    </nav>

    <div class="border-t border-gray-200 p-4">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-red-600 hover:bg-red-50">Keluar</button>
        </form>
    </div>
</aside>
