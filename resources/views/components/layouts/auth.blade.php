@props(['title' => null, 'heading' => null, 'subheading' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
@include('partials.head', ['title' => $title])

<body class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-12 font-sans text-gray-900 antialiased">
    <div class="flex w-full max-w-md flex-col gap-6">
        <a href="{{ route('home') }}" class="self-center text-2xl font-bold tracking-tight text-brand-600">
            FitMate
        </a>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
            @if ($heading)
                <div class="mb-6 flex flex-col gap-1">
                    <h1 class="text-xl font-semibold">{{ $heading }}</h1>

                    @if ($subheading)
                        <p class="text-sm text-gray-500">{{ $subheading }}</p>
                    @endif
                </div>
            @endif

            @include('partials.flash')

            {{ $slot }}
        </div>
    </div>

    @stack('scripts')
</body>
</html>
