@props(['name', 'label', 'type' => 'text', 'hint' => null])

{{--
    Field form standar: label, input, pesan error.
    Untuk type="password" tombol lihat/sembunyikan disediakan lewat Alpine.
--}}
<div x-data="{ show: false }">
    <label for="{{ $name }}" class="block text-sm font-medium text-navy-first">
        {{ $label }}
    </label>

    <div class="relative mt-1.5">
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            @if ($type === 'password')
                x-bind:type="show ? 'text' : 'password'"
            @else
                type="{{ $type }}"
            @endif
            {{ $attributes->class([
                'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm transition outline-none',
                'focus:border-brand-500 focus:ring-2 focus:ring-brand-100',
                'pr-16' => $type === 'password',
                'border-red-400' => $errors->has($name),
                'border-abu-second/60' => ! $errors->has($name),
            ]) }}
        >

        @if ($type === 'password')
            <button
                type="button"
                class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-abu-second hover:text-navy-first"
                x-on:click="show = ! show"
            >
                <span x-show="! show" x-cloak>Lihat</span>
                <span x-show="show" x-cloak>Tutup</span>
            </button>
        @endif
    </div>

    @if ($hint && ! $errors->has($name))
        <p class="mt-1 text-xs text-abu-second">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
