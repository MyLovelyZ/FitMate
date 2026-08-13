@props(['variant' => 'gray'])

@php
    $variants = [
        'gray' => 'bg-gray-100 text-gray-700',
        'brand' => 'bg-brand-50 text-brand-700',
        'success' => 'bg-green-100 text-green-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'danger' => 'bg-red-100 text-red-700',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium',
    $variants[$variant] ?? $variants['gray'],
]) }}>
    {{ $slot }}
</span>
