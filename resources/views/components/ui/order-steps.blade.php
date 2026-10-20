{{--
    Progres pesanan: Belum Dibayar → Diproses → Dalam Perjalanan → Selesai.
    `dates` (opsional) = kunci langkah => teks tanggal di bawah langkahnya.
    Pesanan batal tidak punya langkah, jadi komponen ini tidak merender apa pun.
--}}
@props(['order', 'dates' => [], 'compact' => false])

@use('App\Models\Order')

@php
    $currentStep = $order->buyerStepIndex();
@endphp

@if ($currentStep !== null)
    <ol {{ $attributes->class(['grid grid-cols-4']) }} aria-label="Progres pesanan">
        @foreach (Order::BUYER_STEPS as $index => $step)
            @php
                $isDone = $index < $currentStep;
                $isCurrent = $index === $currentStep;
            @endphp

            <li class="relative flex flex-col items-center text-center" @if ($isCurrent) aria-current="step" @endif>
                {{-- Garis ke langkah sebelumnya --}}
                @if (! $loop->first)
                    <span
                        @class([
                            'absolute right-1/2 top-[9px] h-0.5 w-full -translate-y-1/2',
                            'bg-ink' => $index <= $currentStep,
                            'bg-line' => $index > $currentStep,
                        ])
                        aria-hidden="true"
                    ></span>
                @endif

                <span
                    @class([
                        'relative z-1 flex size-[18px] items-center justify-center rounded-full border-2',
                        'border-ink bg-ink text-cream' => $isDone,
                        'border-ink bg-surface' => $isCurrent,
                        'border-line bg-surface' => ! $isDone && ! $isCurrent,
                    ])
                    aria-hidden="true"
                >
                    @if ($isDone)
                        <svg class="size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m5 12.5 4.5 4.5L19 7.5" />
                        </svg>
                    @elseif ($isCurrent)
                        <span class="size-1.5 rounded-full bg-ink"></span>
                    @endif
                </span>

                <span @class([
                    'mt-2 leading-tight',
                    'text-[10.5px]' => $compact,
                    'text-[12px]' => ! $compact,
                    'font-semibold text-ink' => $isCurrent,
                    'text-ink' => $isDone,
                    'text-muted' => ! $isDone && ! $isCurrent,
                ])>
                    {{ Order::BUYER_STATUS_GROUPS[$step][0] }}
                </span>

                @if (! $compact && ($dates[$step] ?? null))
                    <span class="mt-0.5 text-[11px] text-muted">{{ $dates[$step] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
@endif
