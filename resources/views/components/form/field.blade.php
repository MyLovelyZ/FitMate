@props([
    'name',
    'label' => null,
    'hint' => null,
    'required' => false,
])

<div {{ $attributes->class('flex flex-col gap-1.5') }}>
    @if ($label)
        <label for="{{ $name }}" class="text-sm font-medium text-gray-900">
            {{ $label }}

            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="text-xs text-gray-500">{{ $hint }}</p>
    @endif

    <x-form.error :name="$name" />
</div>
