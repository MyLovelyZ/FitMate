<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-theme="{{ App\Models\User::themeFor(auth()->user()) }}">
    <head>
        @include('partials.head')
    </head>

    <body class="flex min-h-full flex-col bg-bg font-sans text-ink antialiased">
        @include('partials.loading-bar')

        <main class="flex flex-1 items-center justify-center px-side py-12">
            <div class="w-full max-w-[400px]">
                <a href="{{ route('home') }}" wire:navigate class="mb-8 flex flex-col items-center leading-[1.1]" aria-label="FitMate beranda">
                    <span class="font-display text-[22px] tracking-[0.02em]">FITMATE</span>
                    <span class="text-[10px] tracking-[0.22em] text-muted">ARCHIVE</span>
                </a>

                <div class="rounded-md border border-line bg-surface px-[clamp(20px,5vw,32px)] py-8">
                    {{ $slot }}
                </div>
            </div>
        </main>

        @livewireScripts
    </body>
</html>
