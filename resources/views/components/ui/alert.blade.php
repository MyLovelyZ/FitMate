@props(['variant' => 'info', 'dismissible' => true])

@php
    $variants = [
        'info' => 'bg-blue-50 text-blue-800 ring-blue-200',
        'success' => 'bg-green-50 text-green-800 ring-green-200',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'danger' => 'bg-red-50 text-red-800 ring-red-200',
    ];
@endphp

<div
    x-data="{ show: true }"
    x-show="show"
    x-cloak
    x-transition
    {{ $attributes->class([
        'flex items-start gap-3 rounded-lg px-4 py-3 text-sm ring-1 ring-inset',
        $variants[$variant] ?? $variants['info'],
    ]) }}
>
    <div class="flex-1">{{ $slot }}</div>

    @if ($dismissible)
        <button type="button" class="shrink-0 opacity-60 hover:opacity-100" x-on:click="show = false">
            <span class="sr-only">Tutup</span>
            &times;
        </button>
    @endif
</div>
