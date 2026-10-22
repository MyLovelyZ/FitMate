<?php

use App\Models\PayoutAccount;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Store $store;

    /** Alasan penolakan/penangguhan yang akan dibaca pemilik toko. */
    public string $note = '';

    /**
     * NIK lengkap baru dirender server setelah admin memintanya, jadi tidak ikut
     * terkirim di HTML awal halaman.
     */
    public bool $showIdentityNumber = false;

    public function mount(Store $store): void
    {
        $this->store = $store;
    }

    #[Computed]
    public function payoutAccount(): ?PayoutAccount
    {
        return $this->store->payoutAccount();
    }

    /**
     * NIK hanya ditampilkan sebagian sampai admin menekan "Tampilkan".
     */
    #[Computed]
    public function maskedIdentityNumber(): string
    {
        $number = (string) $this->store->identity_number;

        return $number === '' ? '—' : substr($number, 0, 4).str_repeat('•', max(0, strlen($number) - 8)).substr($number, -4);
    }

    #[Computed]
    public function hasIdentityCard(): bool
    {
        return (bool) $this->store->identity_card_path;
    }

    public function approve(): void
    {
        abort_unless(in_array($this->store->status, ['pending', 'rejected'], true), 409);

        $this->store->approve(Auth::user());

        $this->dispatch('notify', message: "{$this->store->name} disetujui dan sekarang aktif.");
    }

    public function reject(): void
    {
        abort_unless($this->store->status === 'pending', 409);

        $this->validate(['note' => ['required', 'string', 'min:10', 'max:1000']], attributes: ['note' => 'reason']);

        $this->store->reject(Auth::user(), $this->note);
        $this->reset('note');

        $this->dispatch('notify', message: 'Pengajuan ditolak. Pemilik bisa memperbaiki lalu mengirim ulang.');
    }

    public function suspend(): void
    {
        abort_unless($this->store->status === 'active', 409);

        $this->validate(['note' => ['required', 'string', 'min:10', 'max:1000']], attributes: ['note' => 'reason']);

        $this->store->suspend(Auth::user(), $this->note);
        $this->reset('note');

        $this->dispatch('notify', message: 'Toko ditangguhkan; produknya disembunyikan dari katalog.');
    }

    public function reactivate(): void
    {
        abort_unless($this->store->status === 'suspended', 409);

        $this->store->reactivate(Auth::user());

        $this->dispatch('notify', message: "{$this->store->name} aktif kembali.");
    }

    public function render(): View
    {
        return $this->view()->title('Tinjau '.$this->store->name);
    }
};
?>

<div class="mx-auto max-w-[1120px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.breadcrumbs :items="[
        'Beranda' => route('home'),
        'Admin' => route('admin.dashboard'),
        'Toko' => route('admin.stores.index', ['status' => $store->status]),
        $store->name => null,
    ]" />

    <header class="mt-4 flex flex-wrap items-end justify-between gap-4 border-b border-line pb-5">
        <div class="flex items-center gap-4">
            <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-cream font-display text-2xl">
                @if ($store->logoUrl())
                    <img src="{{ $store->logoUrl() }}" alt="Logo {{ $store->name }}" class="size-full object-cover">
                @else
                    {{ str($store->name)->substr(0, 1)->upper() }}
                @endif
            </span>
            <div>
                <h1 class="font-display text-[clamp(1.5rem,3.2vw,2rem)] font-normal leading-tight">{{ $store->name }}</h1>
                <p class="mt-1 text-[12.5px] text-muted">
                    Diajukan {{ $store->submitted_at?->format('d M Y, H:i') ?? $store->created_at->format('d M Y') }}
                    &middot; /toko/{{ $store->slug }}
                </p>
            </div>
        </div>

        <span @class([
            'rounded-[3px] px-3 py-1 text-[12px] font-semibold',
            'border border-line bg-cream' => $store->status === 'pending',
            'bg-success/12 text-success' => $store->status === 'active',
            'bg-sale/10 text-sale' => in_array($store->status, ['rejected', 'suspended'], true),
        ])>{{ $store->statusLabel() }}</span>
    </header>

    <div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex flex-col gap-4">
            <section class="rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-5">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Profil Toko</h2>
                <p class="mt-3 whitespace-pre-line text-[13px] leading-relaxed">{{ $store->description }}</p>

                <dl class="mt-4 grid gap-x-6 gap-y-3 border-t border-line pt-4 text-[12.5px] sm:grid-cols-2">
                    <div><dt class="text-muted">Telepon</dt><dd class="font-medium">{{ $store->phone_number ?? '—' }}</dd></div>
                    <div><dt class="text-muted">Email</dt><dd class="font-medium">{{ $store->email ?? '—' }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-muted">Alamat asal pengiriman</dt>
                        <dd class="font-medium">{{ collect([$store->street, $store->city, $store->province, $store->postal_code])->filter()->join(', ') ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-5">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Identitas Pemilik</h2>

                <dl class="mt-3 grid gap-x-6 gap-y-3 text-[12.5px] sm:grid-cols-2">
                    <div><dt class="text-muted">Nama sesuai KTP</dt><dd class="font-medium">{{ $store->identity_name ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-muted">NIK</dt>
                        <dd class="flex items-center gap-2 font-medium tracking-[0.04em]">
                            <span>{{ $showIdentityNumber ? $store->identity_number : $this->maskedIdentityNumber }}</span>
                            @if ($store->identity_number)
                                <button
                                    type="button"
                                    wire:click="$toggle('showIdentityNumber')"
                                    class="cursor-pointer text-[11.5px] font-semibold text-muted underline underline-offset-2 hover:text-ink"
                                >
                                    {{ $showIdentityNumber ? 'Sembunyikan' : 'Tampilkan' }}
                                </button>
                            @endif
                        </dd>
                    </div>
                    <div><dt class="text-muted">Akun pemohon</dt><dd class="font-medium">{{ $store->owner?->name }} &middot; {{ $store->owner?->email }}</dd></div>
                    <div><dt class="text-muted">Bergabung</dt><dd class="font-medium">{{ $store->owner?->created_at?->format('d M Y') }}</dd></div>
                </dl>

                <div class="mt-4 border-t border-line pt-4">
                    <p class="text-[12.5px] text-muted">Foto KTP</p>
                    @if ($this->hasIdentityCard)
                        <a href="{{ route('admin.stores.identity-card', $store) }}" target="_blank" rel="noopener" class="mt-2 block w-fit">
                            <img
                                src="{{ route('admin.stores.identity-card', $store) }}"
                                alt="Foto KTP {{ $store->identity_name }}"
                                loading="lazy"
                                class="max-h-64 rounded-[2px] border border-line object-contain"
                            >
                        </a>
                        <p class="mt-1 text-[11.5px] text-muted">Klik untuk membuka ukuran penuh. Cocokkan nama &amp; NIK dengan isian di atas.</p>
                    @else
                        <p class="mt-1 text-[12.5px] text-sale">Belum ada foto KTP.</p>
                    @endif
                </div>
            </section>

            <section class="rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-5">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Rekening Pencairan</h2>

                @if ($account = $this->payoutAccount)
                    <dl class="mt-3 grid gap-x-6 gap-y-3 text-[12.5px] sm:grid-cols-3">
                        <div><dt class="text-muted">Bank</dt><dd class="font-medium">{{ strtoupper((string) $account->bank_code) }}</dd></div>
                        <div><dt class="text-muted">No. rekening</dt><dd class="font-medium tracking-[0.04em]">{{ $account->account_number }}</dd></div>
                        <div><dt class="text-muted">Atas nama</dt><dd class="font-medium">{{ $account->account_name }}</dd></div>
                    </dl>

                    @if ($store->identity_name && str($account->account_name)->lower()->squish()->value() !== str($store->identity_name)->lower()->squish()->value())
                        <p class="mt-3 rounded-[2px] bg-sale/10 px-3 py-2 text-[12px] text-sale">Nama pemilik rekening berbeda dengan nama di KTP — pastikan sebelum menyetujui.</p>
                    @endif
                @else
                    <p class="mt-3 text-[12.5px] text-sale">Pemohon belum mengisi rekening.</p>
                @endif
            </section>
        </div>

        {{-- Keputusan --}}
        <aside class="flex flex-col gap-4 lg:sticky lg:top-[calc(var(--spacing-header)+16px)]">
            @if ($store->reviewed_at)
                <section class="rounded-md border border-line bg-surface px-[18px] py-4 text-[12.5px]">
                    <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Review Terakhir</h2>
                    <p class="mt-2 text-muted">{{ $store->reviewer?->name ?? 'Admin' }} &middot; {{ $store->reviewed_at->format('d M Y, H:i') }}</p>
                    @if ($store->review_note)
                        <p class="mt-2">{{ $store->review_note }}</p>
                    @endif
                </section>
            @endif

            <section class="rounded-md border border-line bg-surface px-[18px] py-5">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Keputusan</h2>

                @if (in_array($store->status, ['pending', 'rejected'], true))
                    <p class="mt-2 text-[12.5px] text-muted">
                        {{ $store->status === 'rejected' ? 'Pengajuan ini sudah ditolak. Kamu tetap bisa menyetujuinya kalau datanya ternyata sudah benar.' : 'Periksa profil, KTP, dan rekening sebelum memutuskan.' }}
                    </p>

                    <button
                        type="button"
                        wire:click="approve"
                        wire:confirm="Setujui {{ $store->name }}? Pemiliknya langsung bisa berjualan."
                        class="mt-4 inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[2px] bg-ink px-5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity hover:opacity-85 data-loading:opacity-60"
                    >
                        Setujui Toko
                    </button>
                @endif

                @if (in_array($store->status, ['pending', 'active'], true))
                    <form wire:submit="{{ $store->status === 'pending' ? 'reject' : 'suspend' }}" class="mt-4 border-t border-line pt-4">
                        <label for="note" class="block text-[12px] font-semibold">
                            {{ $store->status === 'pending' ? 'Alasan penolakan' : 'Alasan penangguhan' }}
                        </label>
                        <textarea
                            id="note"
                            wire:model="note"
                            rows="3"
                            placeholder="{{ $store->status === 'pending' ? 'Mis. foto KTP buram, nama rekening tidak sesuai KTP.' : 'Mis. menjual barang palsu, banyak komplain tidak ditanggapi.' }}"
                            class="mt-1.5 w-full rounded-[2px] border border-line bg-bg px-3 py-2.5 text-[13px] outline-none transition-colors placeholder:text-muted focus:border-ink"
                        >{{ $note }}</textarea>
                        @error('note') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                        <p class="mt-1 text-[11.5px] text-muted">Alasan ini ditampilkan ke pemilik toko.</p>

                        <button
                            type="submit"
                            class="mt-3 inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[2px] border border-sale px-5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-sale transition-colors hover:bg-sale/10 disabled:opacity-60"
                        >
                            {{ $store->status === 'pending' ? 'Tolak Pengajuan' : 'Tangguhkan Toko' }}
                        </button>
                    </form>
                @endif

                @if ($store->status === 'suspended')
                    <button
                        type="button"
                        wire:click="reactivate"
                        wire:confirm="Aktifkan kembali {{ $store->name }}?"
                        class="mt-4 inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-[2px] bg-ink px-5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity hover:opacity-85 data-loading:opacity-60"
                    >
                        Aktifkan Kembali
                    </button>
                @endif

                @if ($store->canSell())
                    <a href="{{ route('stores.show', $store) }}" wire:navigate class="mt-3 block text-center text-[12.5px] font-medium underline-offset-4 hover:underline">Lihat halaman toko</a>
                @endif
            </section>
        </aside>
    </div>
</div>
