<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Estética Archive')] class extends Component
{
    /**
     * Kartu kategori di bagian atas halaman.
     *
     * Nama class ditulis utuh di sini (bukan dirangkai dari variabel) supaya
     * Tailwind tetap bisa memindainya.
     *
     * @return array<int, array{label: string, surface: string, span: string}>
     */
    #[Computed]
    public function categories(): array
    {
        return [
            [
                'label' => 'Menswear',
                'surface' => 'bg-[linear-gradient(150deg,#cbc4b5,#948d7c)]',
                'span' => 'sm:col-span-2 sm:aspect-video lg:col-span-1 lg:aspect-[3/4]',
            ],
            [
                'label' => 'Womenswear',
                'surface' => 'bg-[linear-gradient(150deg,#d9d2c1,#b3ab98)]',
                'span' => '',
            ],
            [
                'label' => 'Accessories',
                'surface' => 'bg-[linear-gradient(150deg,#c3bcac,#8b8474)]',
                'span' => '',
            ],
        ];
    }
};
?>

<div>
    {{-- Hero --}}
    <section id="top" class="relative flex min-h-[78vh] items-end overflow-hidden">
        <div
            class="absolute inset-0 bg-cover bg-center bg-[linear-gradient(180deg,rgba(23,21,15,0)_40%,rgba(23,21,15,0.55)_100%),linear-gradient(120deg,#d8d2c4,#b9b2a0_45%,#8f8a7c)]"
            aria-hidden="true"
        ></div>

        <div class="relative z-1 mx-auto w-full max-w-[1280px] px-side pb-[clamp(32px,6vw,64px)] text-cream">
            <h1 class="max-w-[14ch] font-display text-[clamp(2.4rem,6vw,4.4rem)] font-normal leading-[1.05] tracking-[-0.01em]">
                Your Daily<br>Go-To Style Mate
            </h1>

            <p class="mt-[clamp(16px,3vw,24px)] max-w-[30ch] rounded border border-[rgba(251,250,247,0.35)] bg-[rgba(23,21,15,0.28)] px-5 py-[17px] text-[clamp(0.95rem,1.6vw,1.05rem)] backdrop-blur-[6px]">
                Considered essentials for everyday movement — designed to layer, built to last.
            </p>

            <a
                href="#categories"
                class="mt-[clamp(20px,3vw,28px)] inline-flex items-center justify-center rounded-[2px] bg-cream px-8 py-4 text-[13.5px] font-semibold uppercase tracking-[0.04em] text-ink transition-opacity duration-200 hover:opacity-85"
            >
                Shop the Archive
            </a>
        </div>
    </section>

    {{-- Kategori --}}
    <section id="categories" class="mx-auto grid max-w-[1280px] grid-cols-1 gap-5 px-side py-[clamp(48px,8vw,96px)] sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->categories as $category)
            <a
                href="#categories"
                class="relative aspect-[3/4] overflow-hidden rounded-md border border-line after:absolute after:inset-0 after:bg-[linear-gradient(180deg,rgba(23,21,15,0)_55%,rgba(23,21,15,0.6)_100%)] after:content-[''] {{ $category['surface'] }} {{ $category['span'] }}"
            >
                <span class="absolute bottom-5 left-5 z-1 rounded-[3px] bg-cream/92 px-[18px] py-2.5 text-[11.5px] font-bold uppercase tracking-[0.14em] text-ink">
                    {{ $category['label'] }}
                </span>
            </a>
        @endforeach
    </section>

    {{-- Ethos --}}
    <section id="ethos" class="px-side py-[clamp(56px,10vw,120px)]">
        <div class="mx-auto flex max-w-[720px] flex-col items-center text-center">
            <svg class="mb-[clamp(20px,4vw,32px)] h-[33px] w-5 text-ink" viewBox="0 0 22 36" fill="none" aria-hidden="true">
                <path d="M11 0 L22 18 L11 36 L0 18 Z" stroke="currentColor" stroke-width="1.2" />
            </svg>

            <h2 class="font-display text-[clamp(1.7rem,4vw,2.5rem)] font-normal leading-tight">
                Structured Minimalism.<br>Uncompromising Form.
            </h2>

            <p class="mt-[clamp(18px,3vw,28px)] text-[clamp(0.95rem,1.6vw,1.05rem)] leading-[1.7] text-muted">
                Every piece in the Archive is pared back to its essential lines — considered fabrics,
                restrained detailing, and a silhouette that holds its shape from morning to night.
                No excess. No noise. Just clothing built to move with you.
            </p>

            <a
                href="#categories"
                class="mt-[clamp(24px,4vw,36px)] inline-flex items-center justify-center rounded-[2px] bg-ink px-8 py-4 text-[13.5px] font-semibold uppercase tracking-[0.04em] text-cream transition-opacity duration-200 hover:opacity-85"
            >
                Discover Our Story
            </a>
        </div>
    </section>
</div>
