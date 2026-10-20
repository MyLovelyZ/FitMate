{{--
    Jejak navigasi halaman. `items` = label => URL; item terakhir adalah halaman
    yang sedang dibuka (URL-nya boleh null), item tengah tanpa URL tampil sebagai teks.

    Ikut mengeluarkan JSON-LD BreadcrumbList supaya jejaknya terbaca mesin pencari.
--}}
@props(['items' => []])

@php
    /** @var array<string, string|null> $items */
    $lastLabel = array_key_last($items);

    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($items)
            ->keys()
            ->map(fn (int|string $label, int $index): array => array_filter([
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => (string) $label,
                'item' => $label === $lastLabel ? null : $items[$label],
            ]))
            ->all(),
    ];
@endphp

@if ($items !== [])
    <nav aria-label="Breadcrumb" {{ $attributes }}>
        <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[12.5px] text-muted">
            @foreach ($items as $label => $url)
                <li class="flex min-w-0 items-center gap-2">
                    @if (! $loop->first)
                        <svg class="size-3 shrink-0 text-line" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m9 18 6-6-6-6" />
                        </svg>
                    @endif

                    @if ($loop->last)
                        <span aria-current="page" class="truncate font-medium text-ink">{{ $label }}</span>
                    @elseif ($url)
                        <a href="{{ $url }}" wire:navigate.hover class="truncate transition-colors duration-150 hover:text-ink">{{ $label }}</a>
                    @else
                        <span class="truncate">{{ $label }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    <script type="application/ld+json">@json($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
@endif
