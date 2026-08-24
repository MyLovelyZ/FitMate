@props(['type' => 'submit'])

<button
    type="{{ $type }}"
    {{ $attributes->class([
        'w-full rounded-lg bg-navy-first px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition',
        'hover:bg-navy-second focus:ring-2 focus:ring-brand-200 focus:outline-none',
        'disabled:cursor-not-allowed disabled:opacity-60',
    ]) }}
>
    {{ $slot }}
</button>
