{{--
    Riwayat pelacakan paket (`shipment_events`), terbaru di atas. Dipakai di
    detail pesanan seller, detail pesanan pembeli, dan nota.
--}}
@props(['shipment'])

@php
    /** @var array<string, string> Status => class warna titik (nama class utuh supaya terpindai Tailwind). */
    $dotColors = [
        'delivered' => 'bg-success',
        'failed' => 'bg-sale',
        'returned' => 'bg-sale',
    ];

    $events = $shipment?->events ?? collect();
@endphp

@if ($events->isNotEmpty())
    <ol {{ $attributes->class(['flex flex-col']) }}>
        @foreach ($events as $event)
            <li wire:key="shipment-event-{{ $event->id }}" class="relative flex gap-3 pb-4 last:pb-0">
                @unless ($loop->last)
                    <span class="absolute left-[4.5px] top-3 h-full w-px bg-line" aria-hidden="true"></span>
                @endunless

                <span @class([
                    'relative mt-1 h-2.5 w-2.5 shrink-0 rounded-full',
                    $loop->first ? ($dotColors[$event->status] ?? 'bg-ink') : 'bg-line',
                ]) aria-hidden="true"></span>

                <div class="min-w-0 flex-1">
                    <p @class(['text-[12.5px] font-semibold', 'text-muted' => ! $loop->first])>
                        {{ App\Models\Shipment::STATUS_LABELS[$event->status] ?? $event->status }}
                    </p>
                    <p class="mt-0.5 text-[12.5px] leading-relaxed text-muted">{{ $event->description }}</p>
                    <p class="mt-0.5 text-[11.5px] text-muted">
                        {{ $event->created_at->format('d M Y, H:i') }}
                        @if ($event->location)
                            &middot; {{ $event->location }}
                        @endif
                    </p>
                </div>
            </li>
        @endforeach
    </ol>
@endif
