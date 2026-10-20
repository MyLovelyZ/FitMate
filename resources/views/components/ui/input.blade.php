@props(['name', 'label', 'type' => 'text', 'hint' => null])

{{--
    Field form standar (palet Estética, sama dengan form profil): label, input, pesan error.
    Untuk type="password" tombol lihat/sembunyikan disediakan lewat Alpine.
--}}
<div x-data="{ show: false }">
    <label for="{{ $name }}" class="block text-[12px] font-semibold">
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
                'h-11 w-full rounded-[2px] border bg-bg px-3 text-[13px] outline-none transition-colors placeholder:text-muted focus:border-ink',
                'pr-16' => $type === 'password',
                'border-sale' => $errors->has($name),
                'border-line' => ! $errors->has($name),
            ]) }}
        >

        @if ($type === 'password')
            <button
                type="button"
                class="absolute inset-y-0 right-0 cursor-pointer px-3 text-[12px] font-medium text-muted transition-colors hover:text-ink"
                x-on:click="show = ! show"
            >
                <span x-show="! show" x-cloak>Lihat</span>
                <span x-show="show" x-cloak>Tutup</span>
            </button>
        @endif
    </div>

    @if ($hint && ! $errors->has($name))
        <p class="mt-1 text-[11.5px] text-muted">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-[12px] text-sale">{{ $message }}</p>
    @enderror
</div>
