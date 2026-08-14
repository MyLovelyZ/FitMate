{{-- Navigasi antar halaman pengaturan akun, dipakai ketiga halaman profil. --}}
<nav class="flex gap-1 overflow-x-auto lg:flex-col">
    <x-ui.nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">Data Akun</x-ui.nav-link>
    <x-ui.nav-link :href="route('profile.body')" :active="request()->routeIs('profile.body')">Ukuran Badan</x-ui.nav-link>
    <x-ui.nav-link :href="route('profile.addresses')" :active="request()->routeIs('profile.addresses')">Alamat</x-ui.nav-link>
</nav>
