{{--
    Tombol utama selebar form (halaman login/register). Kalau `target` diisi,
    teksnya diganti spinner + "Memproses…" selama action Livewire itu berjalan.
--}}
@props(['type' => 'submit', 'target' => null])

<button
    type="{{ $type }}"
    {{ $attributes->class('inline-flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-[2px] bg-ink px-5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85 disabled:cursor-wait disabled:opacity-60') }}
>
    @if ($target)
        <svg wire:loading wire:target="{{ $target }}" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.25" stroke-width="3" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>

        <span wire:loading.remove wire:target="{{ $target }}">{{ $slot }}</span>
        <span wire:loading wire:target="{{ $target }}">Memproses…</span>
    @else
        {{ $slot }}
    @endif
</button>
