@props(['title', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center gap-2 rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center') }}>
    <p class="font-medium text-gray-900">{{ $title }}</p>

    @if ($description)
        <p class="max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-2">{{ $slot }}</div>
    @endif
</div>
