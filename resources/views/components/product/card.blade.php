@props([
    'name',
    'price',
    'href' => '#',
    'image' => null,
    'store' => null,
    'rating' => null,
    'fitStatus' => null,
])

<article {{ $attributes->class('group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:shadow-md') }}>
    <a href="{{ $href }}" class="block aspect-square overflow-hidden bg-gray-100">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $name }}" class="h-full w-full object-cover transition group-hover:scale-105">
        @endif
    </a>

    <div class="flex flex-1 flex-col gap-2 p-4">
        @if ($fitStatus)
            <x-product.fit-badge :status="$fitStatus" class="self-start" />
        @endif

        <a href="{{ $href }}" class="line-clamp-2 text-sm font-medium text-gray-900 hover:text-brand-600">{{ $name }}</a>

        @if ($store)
            <p class="text-xs text-gray-500">{{ $store }}</p>
        @endif

        <p class="mt-auto text-base font-semibold text-gray-900">Rp{{ number_format((float) $price, 0, ',', '.') }}</p>

        @if ($rating)
            <p class="text-xs text-gray-500">&#9733; {{ number_format((float) $rating, 1) }}</p>
        @endif
    </div>
</article>
