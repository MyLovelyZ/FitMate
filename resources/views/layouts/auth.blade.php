<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('partials.head')
    </head>

    <body class="flex min-h-full flex-col bg-navy-second font-sans text-navy-second antialiased">
        <main class="flex flex-1 items-center justify-center px-4 py-12">
            <div class="w-full max-w-md">
                <a href="{{ route('home') }}" wire:navigate class="mb-8 block text-center text-2xl font-semibold tracking-tight text-white">
                    Fit<span class="text-brand-200">Mate</span>
                </a>

                <div class="rounded-2xl bg-white p-8 shadow-xl">
                    {{ $slot }}
                </div>
            </div>
        </main>

        @livewireScripts
    </body>
</html>
