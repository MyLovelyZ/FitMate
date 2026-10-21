{{--
    Navigasi utama "Estética Archive".

    Satu root Alpine membungkus header, panel mobile, dan overlay-nya supaya
    ketiganya berbagi state `open`. `scrolled` hanya dipakai untuk memunculkan
    garis bayangan tipis begitu halaman digulir. `searchOpen` membuka kolom
    pencarian di bawah header; kata kuncinya dikirim ke katalog sebagai `?q=`.
--}}
@php
    /**
     * Label menu katalog => query filter katalog dan apakah ditandai sebagai "sale".
     * Kunci query harus sama dengan properti `#[Url]` di `pages/⚡catalog`.
     *
     * @var array<string, array{query: array<string, string>, isSale: bool}>
     */
    $navLinks = [
        'New Arrivals' => ['query' => ['collection' => 'new'], 'isSale' => false],
        'Men' => ['query' => ['gender' => 'men'], 'isSale' => false],
        'Women' => ['query' => ['gender' => 'women'], 'isSale' => false],
        'Accessories' => ['query' => ['type' => 'aksesoris'], 'isSale' => false],
        'On Sale' => ['query' => ['collection' => 'sale'], 'isSale' => true],
    ];
@endphp

<div
    x-data="{
        open: false,
        scrolled: false,
        searchOpen: false,
        toggleSearch() {
            this.searchOpen = ! this.searchOpen;
            this.open = false;

            if (this.searchOpen) {
                {{-- Isi ulang dari URL, supaya kata kunci yang sedang dipakai katalog bisa langsung disunting. --}}
                this.$refs.searchInput.value = new URLSearchParams(window.location.search).get('q') ?? '';
                this.$nextTick(() => this.$refs.searchInput.select());
            }
        },
        submitSearch() {
            const keyword = this.$refs.searchInput.value.trim();

            if (keyword === '') {
                this.$refs.searchInput.focus();

                return;
            }

            this.searchOpen = false;
            Livewire.navigate(@js(route('products.index')) + '?' + new URLSearchParams({ q: keyword }));
        },
    }"
    x-init="scrolled = window.scrollY > 4"
    x-effect="document.body.classList.toggle('overflow-hidden', open)"
    x-on:scroll.window.passive="scrolled = window.scrollY > 4"
    x-on:keydown.escape.window="open = false; searchOpen = false"
    x-on:resize.window="if (window.innerWidth >= 1024) open = false"
    x-on:livewire:navigating.window="open = false; searchOpen = false"
>
    <a
        class="absolute -left-[9999px] top-0 z-200 bg-ink px-5 py-3 text-bg focus:left-3 focus:top-3"
        href="#main"
    >
        Lewati ke konten
    </a>

    <header
        class="sticky top-0 z-100 border-b border-line bg-bg/92 backdrop-blur-[10px] transition-shadow duration-200"
        x-bind:class="scrolled ? 'shadow-[0_1px_0_rgba(10,25,47,0.06)]' : 'shadow-none'"
        x-on:click.outside="searchOpen = false"
    >
        <div class="mx-auto flex h-header items-center justify-between gap-6 px-side">
            <a
                href="{{ route('home') }}"
                wire:navigate.hover
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
                    @foreach ($navLinks as $label => $link)
                        <li>
                            <a
                                href="{{ route('products.index', $link['query']) }}"
                                wire:navigate.hover
                                class="relative py-1 text-[13.5px] font-medium after:absolute after:bottom-0 after:left-0 after:h-px after:w-0 after:bg-ink after:transition-[width] after:duration-250 after:content-[''] hover:after:w-full {{ $link['isSale'] ? 'text-sale' : '' }}"
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
                    class="inline-flex size-[38px] cursor-pointer items-center justify-center rounded-full hover:bg-line"
                    x-bind:class="searchOpen ? 'bg-line' : ''"
                    aria-label="Cari"
                    aria-controls="siteSearch"
                    x-bind:aria-expanded="searchOpen"
                    x-on:click="toggleSearch()"
                >
                    <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                </button>

                {{-- Keranjang khusus pembeli; tamu diarahkan ke login oleh middleware auth. --}}
                @if (! auth()->check() || auth()->user()->canShop())
                    @php
                        $cartQuantity = auth()->user()?->cartQuantity() ?? 0;
                    @endphp

                    {{-- Lencana jumlah ikut diperbarui lewat event `cart-updated` dari komponen Livewire. --}}
                    <a
                        href="{{ route('user.cart') }}"
                        wire:navigate.hover
                        class="relative hidden size-[38px] items-center justify-center rounded-full hover:bg-line lg:inline-flex"
                        aria-label="Keranjang belanja"
                        x-data="{ count: {{ $cartQuantity }} }"
                        x-on:cart-updated.window="count = $event.detail.count"
                    >
                        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 8h12l-1 12H7L6 8Z" />
                            <path d="M9 8V6a3 3 0 0 1 6 0v2" />
                        </svg>

                        <span
                            data-cart-count
                            x-show="count > 0"
                            x-text="count > 99 ? '99+' : count"
                            @if ($cartQuantity === 0) x-cloak @endif
                            class="absolute -right-0.5 -top-0.5 min-w-[18px] rounded-full bg-sale px-1 text-center text-[10px] font-bold leading-[18px] text-cream"
                        >{{ $cartQuantity > 99 ? '99+' : $cartQuantity }}</span>
                    </a>
                @endif

                @auth
                    {{-- Menu akun: avatar membuka daftar tautan sesuai role + tombol keluar. --}}
                    <div
                        class="relative ml-1 hidden lg:block"
                        x-data="{ menuOpen: false }"
                        x-on:click.outside="menuOpen = false"
                        x-on:keydown.escape.window="menuOpen = false"
                        x-on:livewire:navigating.window="menuOpen = false"
                    >
                        <button
                            type="button"
                            class="flex cursor-pointer items-center rounded-full p-0.5 ring-offset-2 ring-offset-bg transition-shadow hover:ring-1 hover:ring-line"
                            aria-haspopup="menu"
                            aria-label="Menu akun {{ auth()->user()->name }}"
                            x-bind:aria-expanded="menuOpen"
                            x-on:click="menuOpen = ! menuOpen"
                        >
                            <x-ui.avatar :user="auth()->user()" class="size-[34px] text-[12px]" />
                        </button>

                        <div
                            x-cloak
                            x-show="menuOpen"
                            x-transition.opacity.duration.150ms
                            class="absolute right-0 top-[calc(100%+10px)] z-110 w-60 rounded-md border border-line bg-surface py-2 shadow-[0_12px_32px_rgba(10,25,47,0.12)]"
                            role="menu"
                        >
                            <div class="border-b border-line px-4 pb-3 pt-1">
                                <p class="truncate text-[13px] font-semibold">{{ auth()->user()->name }}</p>
                                <p class="truncate text-[12px] text-muted">{{ auth()->user()->email }}</p>
                            </div>

                            @foreach (auth()->user()->accountLinks() as $label => $routeName)
                                <a
                                    href="{{ route($routeName) }}"
                                    wire:navigate.hover
                                    role="menuitem"
                                    class="block px-4 py-2.5 text-[13px] transition-colors hover:bg-bg"
                                >
                                    {{ $label }}
                                </a>
                            @endforeach

                            <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-line pt-1">
                                @csrf

                                <button
                                    type="submit"
                                    role="menuitem"
                                    class="block w-full cursor-pointer px-4 py-2.5 text-left text-[13px] text-sale transition-colors hover:bg-bg"
                                    aria-label="Keluar dari {{ auth()->user()->name }}"
                                >
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a
                        href="{{ route('login') }}"
                        wire:navigate.hover
                        class="ml-1.5 hidden text-[13.5px] font-medium lg:inline-flex"
                    >
                        Masuk
                    </a>

                    <a
                        href="{{ route('register') }}"
                        wire:navigate.hover
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
                    x-on:click="open = ! open; searchOpen = false"
                >
                    <span class="block h-[1.5px] w-5 bg-ink transition-transform duration-200" x-bind:class="open ? 'translate-y-[6.5px] rotate-45' : ''"></span>
                    <span class="block h-[1.5px] w-5 bg-ink transition-opacity duration-200" x-bind:class="open ? 'opacity-0' : ''"></span>
                    <span class="block h-[1.5px] w-5 bg-ink transition-transform duration-200" x-bind:class="open ? '-translate-y-[6.5px] -rotate-45' : ''"></span>
                </button>
            </div>
        </div>

        {{-- Kolom pencarian. Tanpa JS pun form-nya tetap jalan sebagai GET biasa ke katalog. --}}
        <div
            id="siteSearch"
            x-cloak
            x-show="searchOpen"
            x-transition.opacity.duration.150ms
            class="absolute inset-x-0 top-full border-b border-line bg-surface shadow-[0_12px_32px_rgba(10,25,47,0.08)]"
        >
            <form
                action="{{ route('products.index') }}"
                method="GET"
                role="search"
                class="mx-auto flex max-w-[1280px] items-center gap-3 px-side py-3.5"
                x-on:submit.prevent="submitSearch()"
            >
                <label for="siteSearchInput" class="sr-only">Cari produk</label>

                <svg class="size-[18px] shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>

                <input
                    id="siteSearchInput"
                    x-ref="searchInput"
                    type="search"
                    name="q"
                    maxlength="100"
                    autocomplete="off"
                    enterkeyhint="search"
                    placeholder="Cari produk, mis. kaos, sneakers, tas…"
                    class="h-11 min-w-0 flex-1 bg-transparent text-[15px] outline-none placeholder:text-muted"
                >

                <button
                    type="submit"
                    class="shrink-0 cursor-pointer rounded-[2px] bg-ink px-5 py-2.5 text-[12px] font-semibold uppercase tracking-[0.04em] text-cream transition-opacity duration-200 hover:opacity-85"
                >
                    Cari
                </button>
            </form>
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
                @foreach ($navLinks as $label => $link)
                    <li>
                        <a
                            href="{{ route('products.index', $link['query']) }}"
                            wire:navigate.hover
                            @class([
                                'block border-b border-line py-3.5 text-base font-medium',
                                'text-sale' => $link['isSale'],
                            ])
                        >
                            {{ $label }}
                        </a>
                    </li>
                @endforeach

                @auth
                    <li class="flex items-center gap-3 pt-5">
                        <x-ui.avatar :user="auth()->user()" class="size-9 text-[12px]" />
                        <span class="text-[13px] text-muted">Halo, {{ auth()->user()->name }}</span>
                    </li>
                    @foreach (auth()->user()->accountLinks() as $label => $routeName)
                        <li>
                            <a class="block py-2 text-base font-medium" href="{{ route($routeName) }}" wire:navigate.hover>{{ $label }}</a>
                        </li>
                    @endforeach
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <button type="submit" class="py-2 text-base font-medium">Keluar</button>
                        </form>
                    </li>
                @else
                    <li>
                        <a class="block pb-1 pt-5 text-base font-medium" href="{{ route('login') }}" wire:navigate.hover>Masuk</a>
                    </li>
                    <li>
                        <a class="block py-1 text-base font-medium" href="{{ route('register') }}" wire:navigate.hover>Daftar</a>
                    </li>
                @endauth
            </ul>
        </nav>
    </div>

    <div
        x-cloak
        class="fixed inset-0 top-header z-98 bg-[rgba(10,25,47,0.25)] transition-opacity duration-200 lg:hidden"
        x-bind:class="open ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'"
        x-on:click="open = false"
        aria-hidden="true"
    ></div>
</div>
