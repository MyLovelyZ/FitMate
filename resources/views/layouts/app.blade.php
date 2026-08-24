<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
    <head>
        @include('partials.head')
    </head>

    <body class="flex min-h-full flex-col bg-bg font-sans text-base leading-normal text-ink antialiased">
        @include('partials.navbar')

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-line">
            <div class="mx-auto flex max-w-[1280px] flex-col items-center gap-5 px-side py-[clamp(32px,6vw,40px)] text-center lg:flex-row lg:justify-between lg:text-left">
                <a href="{{ route('home') }}" wire:navigate class="font-display text-xl">FITMATE</a>

                <nav aria-label="Footer">
                    <ul class="flex list-none flex-wrap justify-center gap-x-7 gap-y-5">
                        <li><a class="text-[13px] text-muted hover:text-ink" href="{{ route('home') }}#categories">Collections</a></li>
                        <li><a class="text-[13px] text-muted hover:text-ink" href="{{ route('home') }}#ethos">About</a></li>
                        <li><a class="text-[13px] text-muted hover:text-ink" href="{{ route('home') }}#ethos">FAQ</a></li>
                        <li><a class="text-[13px] text-muted hover:text-ink" href="{{ route('home') }}#ethos">Contact Us</a></li>
                    </ul>
                </nav>

                <p class="text-[11.5px] tracking-[0.03em] text-muted">
                    &copy; {{ date('Y') }} FITMATE ARCHIVE. ALL RIGHTS RESERVED.
                </p>
            </div>
        </footer>

        @livewireScripts
    </body>
</html>
