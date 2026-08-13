@props([
    'name' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
])

<select
    @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
    {{ $attributes->class([
        'w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-300 ring-inset',
        'focus:ring-2 focus:ring-brand-600 focus:outline-none',
        'ring-red-500 focus:ring-red-500' => $name && $errors->has($name),
    ]) }}
>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" @selected(($name ? old($name, $selected) : $selected) == $optionValue)>
            {{ $optionLabel }}
        </option>
    @endforeach

    {{ $slot }}
</select>
