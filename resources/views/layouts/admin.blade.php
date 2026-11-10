{{--
    Master layout back-office (admin & superadmin), terpisah dari `layouts/app`
    milik etalase: tanpa navbar belanja, keranjang, dan footer toko.

    - Halaman Livewire: `#[Layout('layouts::admin')]` di komponen `pages::admin-*`.
    - View dari controller (mis. `admin/transactions`): `<x-layouts::admin title="...">`.
--}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth" data-theme="{{ App\Models\User::themeFor(auth()->user()) }}">
    <head>
        @include('partials.head')
    </head>

    <body class="flex min-h-full flex-col bg-bg font-sans text-base leading-normal text-ink antialiased">
        @include('partials.loading-bar')

        <header class="sticky top-0 z-50 border-b border-line bg-surface">
            <div class="mx-auto flex h-14 max-w-[1280px] items-center justify-between gap-4 px-side">
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-baseline gap-2">
                    <span class="font-display text-lg">FITMATE</span>
                    <span class="text-[10.5px] font-bold uppercase tracking-[0.14em] text-muted">Back-Office</span>
                </a>

                <nav class="flex items-center gap-4 text-[12.5px]" aria-label="Menu back-office">
                    <a href="{{ route('admin.transactions.index') }}" class="hidden font-semibold hover:underline md:inline">Transaksi</a>
                    {{-- Seller Center admin: katalog semua toko + tambah produk. --}}
                    <a href="{{ route('admin.products.index') }}" wire:navigate class="font-semibold hover:underline">Seller Center</a>
                </nav>

                <div class="flex items-center gap-4 text-[12.5px]">
                    <a href="{{ route('home') }}" wire:navigate class="hidden text-muted transition-colors hover:text-ink sm:inline">Lihat Situs</a>
                    <a href="{{ route('profile') }}" wire:navigate class="hidden font-medium hover:underline sm:inline">{{ auth()->user()?->name }}</a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="cursor-pointer font-semibold text-sale transition-opacity hover:opacity-80">Keluar</button>
                    </form>
                </div>
            </div>
        </header>

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-line">
            <p class="mx-auto max-w-[1280px] px-side py-5 text-[11.5px] tracking-[0.03em] text-muted">
                FitMate Back-Office &middot; {{ ucfirst((string) auth()->user()?->role) }}
            </p>
        </footer>

        @include('partials.toast')

        @livewireScripts
    </body>
</html>
