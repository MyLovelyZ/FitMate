{{--
    Alur pengajuan toko di sisi kanan halaman "Buka Toko". `current` = langkah aktif (1–3).
--}}
@props(['current' => 1])

@php
    /** @var array<int, array{0: string, 1: string}> Langkah => [judul, penjelasan]. */
    $steps = [
        1 => ['Isi formulir', 'Profil toko, alamat asal pengiriman, KTP, dan rekening pencairan.'],
        2 => ['Ditinjau admin', 'Biasanya 1–2 hari kerja. Kalau ada yang kurang, kamu bisa memperbaiki dan mengirim ulang.'],
        3 => ['Mulai berjualan', 'Toko aktif, punya halaman sendiri, dan produkmu tampil di katalog.'],
    ];
@endphp

<aside class="h-fit rounded-md border border-line bg-surface px-[18px] py-5">
    <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Alur Pengajuan</h2>

    <ol class="mt-4 flex flex-col gap-4">
        @foreach ($steps as $number => [$title, $text])
            <li class="flex gap-3" @if ($number === $current) aria-current="step" @endif>
                <span @class([
                    'flex size-6 shrink-0 items-center justify-center rounded-full border text-[11px] font-bold',
                    'border-ink bg-ink text-cream' => $number <= $current,
                    'border-line text-muted' => $number > $current,
                ])>{{ $number }}</span>
                <div>
                    <p @class(['text-[13px] font-semibold', 'text-muted' => $number > $current])>{{ $title }}</p>
                    <p class="mt-0.5 text-[12px] leading-relaxed text-muted">{{ $text }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</aside>
