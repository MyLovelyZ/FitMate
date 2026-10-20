{{--
    Judul halaman area role (seller, CS, admin, dll.) plus tab antar-halaman
    di area yang sama. `links` berisi nama route => label tab, `breadcrumbs` berisi
    label => URL (lihat `x-ui.breadcrumbs`).
--}}
@props([
    'title',
    'description' => null,
    'links' => [],
    'breadcrumbs' => [],
])

<header class="border-b border-line pb-5">
    <x-ui.breadcrumbs :items="$breadcrumbs" class="mb-4" />

    <h1 class="font-display text-[clamp(1.5rem,3.2vw,2rem)] font-normal leading-tight">{{ $title }}</h1>

    @if ($description)
        <p class="mt-1.5 text-[13px] text-muted">{{ $description }}</p>
    @endif

    @if ($links !== [])
        <nav class="mt-5 flex flex-wrap gap-2" aria-label="Menu area">
            @foreach ($links as $routeName => $label)
                {{-- `data-current` dipasang Livewire di sisi browser, jadi tetap benar setelah komponen re-render. --}}
                <a
                    href="{{ route($routeName) }}"
                    wire:navigate.hover
                    class="rounded-[2px] border border-line px-4 py-2 text-[12px] font-semibold uppercase tracking-[0.04em] transition-colors duration-150 hover:border-ink data-current:border-ink data-current:bg-ink data-current:text-cream"
                >
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    @endif
</header>
