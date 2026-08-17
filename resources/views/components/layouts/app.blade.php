@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
@include('partials.head', ['title' => $title])

<body class="flex min-h-screen flex-col bg-gray-50 font-sans text-gray-900 antialiased">
    @include('partials.navbar')

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
        @include('partials.flash')

        {{ $slot }}
    </main>

    @include('partials.footer')

    @stack('scripts')

    @livewireScripts
</body>
</html>
