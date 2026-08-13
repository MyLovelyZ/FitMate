@props(['title' => null, 'area' => 'seller'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
@include('partials.head', ['title' => $title])

<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased" x-data="{ sidebarOpen: false }">
    @include('partials.sidebar', ['area' => $area])

    <div class="lg:pl-64">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-gray-200 bg-white px-4 sm:px-6">
            <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden" x-on:click="sidebarOpen = true">
                <span class="sr-only">Buka menu</span>
                &#9776;
            </button>

            <span class="text-sm font-medium text-gray-500">{{ $title ?? ucfirst($area) }}</span>

            <div class="ml-auto flex items-center gap-3">
                <a href="{{ route('home') }}" class="text-sm text-gray-500 hover:text-brand-600">Lihat situs</a>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            @include('partials.flash')

            {{ $slot }}
        </main>
    </div>

    @stack('scripts')
</body>
</html>
