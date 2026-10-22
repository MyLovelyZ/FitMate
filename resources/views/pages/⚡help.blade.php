<?php

use App\Models\EscrowHold;
use App\Models\Order;
use App\Models\Shipment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tujuan link "FAQ" dan "Contact Us" di footer. Angka-angkanya dibaca dari
 * konstanta model supaya jawaban ikut berubah kalau aturannya berubah.
 */
new #[Title('Bantuan')] class extends Component
{
    /**
     * Pertanyaan => jawaban (HTML aman, ditulis di sini).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function questions(): array
    {
        $shipping = collect(Shipment::OPTIONS)
            ->map(fn (array $option): string => $option['label'].' Rp '.number_format($option['cost'], 0, ',', '.'))
            ->join(', ', ' atau ');

        return [
            'Bagaimana cara memilih ukuran yang pas?' => 'Semua toko di FitMate memakai standar ukuran yang sama, jadi ukuran M di satu toko sama dengan M di toko lain. Simpan ukuran badanmu di <a href="'.e(route('profile', ['tab' => 'ukuran'])).'" class="font-semibold text-ink underline underline-offset-4">Profil › Ukuran Badan</a> supaya mudah dicocokkan saat belanja.',
            'Berapa lama batas waktu pembayaran?' => 'Setelah pesanan dibuat, kamu punya waktu '.Order::PAYMENT_WINDOW_HOURS.' jam untuk membayar lewat virtual account atau QRIS. Lewat dari itu pesanan dibatalkan otomatis dan barangnya kembali ke stok toko.',
            'Apakah uangku aman?' => 'Pembayaranmu ditahan FitMate, bukan langsung diterima penjual. Dana baru diteruskan ke penjual setelah kamu menekan "Pesanan Diterima", atau otomatis '.EscrowHold::AUTO_RELEASE_DAYS.' hari setelah paket sampai kalau tidak ada keluhan.',
            'Berapa ongkos kirimnya?' => 'Ongkir dihitung per toko, karena tiap toko mengirim paketnya sendiri: '.e($shipping).'.',
            'Bisakah aku membatalkan pesanan?' => 'Sebelum dibayar, pesanan bisa kamu batalkan sendiri dari halaman pesanan. Setelah dibayar, penjual masih bisa membatalkan sebelum paket dikirim, dan seluruh dananya dikembalikan ke saldo FitMate-mu.',
            'Bagaimana cara berjualan di FitMate?' => 'Ajukan tokomu di halaman <a href="'.e(route('stores.create')).'" class="font-semibold text-ink underline underline-offset-4">Buka Toko</a>. Setelah data toko dan KTP diverifikasi admin, produkmu bisa tampil di katalog.',
        ];
    }

    /**
     * Ke mana harus pergi untuk tiap kebutuhan: label => [keterangan, nama route, parameter].
     *
     * @return array<string, array{0: string, 1: string, 2: array<string, string>}>
     */
    #[Computed]
    public function contacts(): array
    {
        return [
            'Pesanan Saya' => ['Bayar, batalkan, lacak paket, atau konfirmasi pesanan diterima.', 'user.orders', []],
            'Ukuran Badan' => ['Perbarui ukuran badan yang dipakai saat memilih ukuran.', 'profile', ['tab' => 'ukuran']],
            'Buka Toko' => ['Ajukan toko atau cek status pengajuanmu.', 'stores.create', []],
        ];
    }
};
?>

<div class="mx-auto max-w-[800px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.breadcrumbs :items="['Beranda' => route('home'), 'Bantuan' => null]" />

    <header class="mt-4 border-b border-line pb-5">
        <h1 class="font-display text-[clamp(1.6rem,3.4vw,2.1rem)] font-normal leading-tight">Bantuan</h1>
        <p class="mt-1.5 text-[13px] text-muted">Jawaban singkat soal ukuran, pembayaran, pengiriman, dan berjualan di FitMate.</p>
    </header>

    <section id="faq" class="mt-6 scroll-mt-[calc(var(--spacing-header)+16px)]">
        <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Pertanyaan Umum</h2>

        <div class="mt-3 divide-y divide-line rounded-md border border-line bg-surface">
            @foreach ($this->questions as $question => $answer)
                <details wire:key="faq-{{ $loop->index }}" class="group px-[18px]">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 text-[13.5px] font-medium [&::-webkit-details-marker]:hidden">
                        {{ $question }}
                        <svg class="size-4 shrink-0 text-muted transition-transform duration-200 group-open:rotate-45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                    </summary>

                    <p class="pb-4 text-[13px] leading-relaxed text-muted">{!! $answer !!}</p>
                </details>
            @endforeach
        </div>
    </section>

    <section id="kontak" class="mt-10 scroll-mt-[calc(var(--spacing-header)+16px)]">
        <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Masih Butuh Bantuan?</h2>

        <ul class="mt-3 grid gap-3 sm:grid-cols-3">
            @foreach ($this->contacts as $label => [$description, $routeName, $parameters])
                <li wire:key="contact-{{ $routeName }}">
                    <a
                        href="{{ route($routeName, $parameters) }}"
                        wire:navigate
                        class="flex h-full flex-col rounded-md border border-line bg-surface px-[18px] py-4 transition-colors duration-150 hover:border-ink"
                    >
                        <span class="text-[13.5px] font-semibold">{{ $label }}</span>
                        <span class="mt-1 text-[12.5px] leading-relaxed text-muted">{{ $description }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
</div>
