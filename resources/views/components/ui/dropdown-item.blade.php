@props(['href' => null])

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class('block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50') }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->class('block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50')->merge(['type' => 'button']) }}>
        {{ $slot }}
    </button>
@endif
