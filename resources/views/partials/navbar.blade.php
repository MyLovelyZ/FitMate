{{--
    Navigasi utama "Estética Archive".

    Satu root Alpine membungkus header, panel mobile, dan overlay-nya supaya
    ketiganya berbagi state `open`. `scrolled` hanya dipakai untuk memunculkan
    garis bayangan tipis begitu halaman digulir.
--}}
@php
    /** @var array<string, bool> Label menu katalog => apakah ditandai sebagai "sale". */
    $navLinks = [
        'New Arrivals' => false,
        'Men' => false,
        'Women' => false,
        'Accessories' => false,
        'On Sale' => true,
    ];
@endphp

<div
    x-data="{ open: false, scrolled: false }"
    x-init="scrolled = window.scrollY > 4"
    x-effect="document.body.classList.toggle('overflow-hidden', open)"
    x-on:scroll.window.passive="scrolled = window.scrollY > 4"
    x-on:keydown.escape.window="open = false"
    x-on:resize.window="if (window.innerWidth >= 1024) open = false"
    x-on:livewire:navigating.window="open = false"
>
    <a
        class="absolute -left-[9999px] top-0 z-200 bg-ink px-5 py-3 text-bg focus:left-3 focus:top-3"
        href="#main"
    >
        Lewati ke konten
    </a>

    <header
        class="sticky top-0 z-100 border-b border-line bg-bg/92 backdrop-blur-[10px] transition-shadow duration-200"
        x-bind:class="scrolled ? 'shadow-[0_1px_0_rgba(23,21,15,0.06)]' : 'shadow-none'"
    >
        <div class="mx-auto flex h-header items-center justify-between gap-6 px-side">
            <a
                href="{{ route('home') }}"
                wire:navigate
                class="flex shrink-0 items-center gap-3"
                aria-label="FitMate beranda"
            >
                <span class="relative h-[30px] w-10 shrink-0" aria-hidden="true">
                    <span class="absolute left-0 top-1/2 size-[26px] -translate-y-1/2 rounded-full border-[1.5px] border-ink bg-ink"></span>
                    <span class="absolute left-[14px] top-1/2 size-[26px] -translate-y-1/2 rounded-full border-[1.5px] border-ink bg-transparent"></span>
                </span>

                <span class="flex flex-col leading-[1.1]">
                    <span class="font-display text-[19px] tracking-[0.02em]">FITMATE</span>
                    <span class="text-[10px] tracking-[0.22em] text-muted">ARCHIVE</span>
                </span>
            </a>

            <nav class="hidden lg:block" aria-label="Utama">
                <ul class="flex list-none items-center gap-8">
                    @foreach ($navLinks as $label => $isSale)
                        <li>
                            <a
                                href="{{ route('home') }}#categories"
                                class="relative py-1 text-[13.5px] font-medium after:absolute after:bottom-0 after:left-0 after:h-px after:w-0 after:bg-ink after:transition-[width] after:duration-250 after:content-[''] hover:after:w-full {{ $isSale ? 'text-sale' : '' }}"
                            >
                                {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="flex shrink-0 items-center gap-1.5">
                <button
                    type="button"
                    class="hidden size-[38px] cursor-pointer items-center justify-center rounded-full hover:bg-line lg:inline-flex"
                    aria-label="Cari"
                >
                    <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                </button>

                <button
                    type="button"
                    class="hidden size-[38px] cursor-pointer items-center justify-center rounded-full hover:bg-line lg:inline-flex"
                    aria-label="Tas belanja"
                >
                    <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 8h12l-1 12H7L6 8Z" />
                        <path d="M9 8V6a3 3 0 0 1 6 0v2" />
                    </svg>
                </button>

                @auth
                    <form method="POST" action="{{ route('logout') }}" class="hidden lg:block">
                        @csrf

                        <button
                            type="submit"
                            class="inline-flex size-[38px] cursor-pointer items-center justify-center rounded-full hover:bg-line"
                            aria-label="Keluar dari {{ auth()->user()->name }}"
                        >
                            <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 17v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v2" />
                                <path d="M11 12h10m0 0-3-3m3 3-3 3" />
                            </svg>
                        </button>
                    </form>
                @else
                    <a
                        href="{{ route('login') }}"
                        wire:navigate
                        class="ml-1.5 hidden text-[13.5px] font-medium lg:inline-flex"
                    >
                        Masuk
                    </a>

                    <a
                        href="{{ route('register') }}"
                        wire:navigate
                        class="ml-2 hidden rounded-[2px] bg-ink px-5 py-2.5 text-[12px] font-semibold uppercase tracking-[0.04em] text-cream transition-opacity duration-200 hover:opacity-85 lg:inline-flex"
                    >
                        Daftar
                    </a>
                @endauth

                <button
                    type="button"
                    class="flex size-[38px] cursor-pointer flex-col items-center justify-center gap-[5px] lg:hidden"
                    aria-controls="mobileNav"
                    x-bind:aria-expanded="open"
                    x-bind:aria-label="open ? 'Tutup menu' : 'Buka menu'"
                    x-on:click="open = ! open"
                >
                    <span class="block h-[1.5px] w-5 bg-ink transition-transform duration-200" x-bind:class="open ? 'translate-y-[6.5px] rotate-45' : ''"></span>
                    <span class="block h-[1.5px] w-5 bg-ink transition-opacity duration-200" x-bind:class="open ? 'opacity-0' : ''"></span>
                    <span class="block h-[1.5px] w-5 bg-ink transition-transform duration-200" x-bind:class="open ? '-translate-y-[6.5px] -rotate-45' : ''"></span>
                </button>
            </div>
        </div>
    </header>

    {{-- Panel navigasi versi mobile, muncul tepat di bawah header. --}}
    <div
        id="mobileNav"
        x-cloak
        class="fixed inset-x-0 top-header z-99 border-b border-line bg-surface transition-[transform,opacity] duration-200 lg:hidden"
        x-bind:class="open ? 'translate-y-0 opacity-100 pointer-events-auto' : '-translate-y-2 opacity-0 pointer-events-none'"
        x-bind:aria-hidden="! open"
    >
        <nav aria-label="Mobile" x-on:click="open = false">
            <ul class="flex list-none flex-col px-side pb-6 pt-3">
                @foreach ($navLinks as $label => $isSale)
                    <li>
                        <a
                            href="{{ route('home') }}#categories"
                            @class([
                                'block border-b border-line py-3.5 text-base font-medium',
                                'text-sale' => $isSale,
                            ])
                        >
                            {{ $label }}
                        </a>
                    </li>
                @endforeach

                @auth
                    <li class="pt-5 text-[13px] text-muted">Halo, {{ auth()->user()->name }}</li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <button type="submit" class="py-2 text-base font-medium">Keluar</button>
                        </form>
                    </li>
                @else
                    <li>
                        <a class="block pb-1 pt-5 text-base font-medium" href="{{ route('login') }}" wire:navigate>Masuk</a>
                    </li>
                    <li>
                        <a class="block py-1 text-base font-medium" href="{{ route('register') }}" wire:navigate>Daftar</a>
                    </li>
                @endauth
            </ul>
        </nav>
    </div>

    <div
        x-cloak
        class="fixed inset-0 top-header z-98 bg-[rgba(23,21,15,0.25)] transition-opacity duration-200 lg:hidden"
        x-bind:class="open ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'"
        x-on:click="open = false"
        aria-hidden="true"
    ></div>
</div>
