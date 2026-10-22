{{--
    Lencana status pesanan untuk pembeli. `group` = kunci `Order::BUYER_STATUS_GROUPS`,
    jadi warnanya sama di daftar pesanan maupun halaman detail.
--}}
@props(['order'])

@php
    /** @var array<string, string> Kelompok status => class warna utuh (supaya terpindai Tailwind). */
    $colors = [
        'belum-dibayar' => 'bg-sale/10 text-sale',
        'diproses' => 'border border-line bg-cream text-ink',
        'dikirim' => 'bg-ink text-cream',
        'selesai' => 'bg-success/12 text-success',
        'dibatalkan' => 'bg-line text-muted',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center whitespace-nowrap rounded-[3px] px-2 py-0.5 text-[11px] font-semibold',
    $colors[$order->buyerStatusGroup()] ?? $colors['diproses'],
]) }}>
    {{ $order->buyerStatusLabel() }}
</span>
