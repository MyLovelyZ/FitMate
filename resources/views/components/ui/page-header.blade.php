@props(['title', 'subtitle' => null, 'actions' => null])

<div {{ $attributes->class('mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between') }}>
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">{{ $title }}</h1>

        @if ($subtitle)
            <p class="text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>

    @if ($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endif
</div>
