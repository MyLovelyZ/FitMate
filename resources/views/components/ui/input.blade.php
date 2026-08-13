@props([
    'name' => null,
    'type' => 'text',
    'value' => null,
])

<input
    type="{{ $type }}"
    @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
    value="{{ $name ? old($name, $value) : $value }}"
    {{ $attributes->class([
        'w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-300 ring-inset',
        'placeholder:text-gray-400 focus:ring-2 focus:ring-brand-600 focus:outline-none',
        'disabled:cursor-not-allowed disabled:bg-gray-50',
        'ring-red-500 focus:ring-red-500' => $name && $errors->has($name),
    ]) }}
>
