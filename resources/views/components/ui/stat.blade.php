@props([
    'label',
    'value',
])

<div class="rounded-md border border-line bg-surface px-[18px] py-4">
    <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">{{ $label }}</p>
    <p class="mt-1.5 font-display text-[clamp(1.4rem,2.6vw,1.75rem)] leading-tight">{{ $value }}</p>
</div>
