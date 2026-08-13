@props(['heading' => null, 'footer' => null])

<div {{ $attributes->class('rounded-xl border border-gray-200 bg-white shadow-sm') }}>
    @if ($heading)
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="font-semibold text-gray-900">{{ $heading }}</h2>
        </div>
    @endif

    <div class="p-5">
        {{ $slot }}
    </div>

    @if ($footer)
        <div class="border-t border-gray-200 bg-gray-50 px-5 py-3">
            {{ $footer }}
        </div>
    @endif
</div>
