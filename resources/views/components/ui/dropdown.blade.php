@props(['align' => 'right', 'width' => 'w-52', 'trigger' => null])

<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    <div x-on:click="open = ! open">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute z-50 mt-2 {{ $width }} {{ $align === 'left' ? 'left-0' : 'right-0' }} overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
    >
        {{ $slot }}
    </div>
</div>
