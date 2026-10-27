<?php

use App\Models\EscrowHold;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Buka Toko')] class extends Component
{
    use WithFileUploads;

    /**
     * Bank tujuan pencairan dana: kode di `payout_accounts.bank_code` => nama.
     *
     * @var array<string, string>
     */
    public const BANKS = [
        'bca' => 'BCA',
        'bni' => 'BNI',
        'bri' => 'BRI',
        'mandiri' => 'Mandiri',
        'bsi' => 'BSI',
        'cimb' => 'CIMB Niaga',
        'permata' => 'Permata',
    ];

    /**
     * Langkah formulir pengajuan: nomor => [judul, penjelasan, field]. Field
     * divalidasi setiap kali "Lanjut" ditekan, jadi kesalahan ketahuan di
     * langkahnya sendiri. Profil toko sengaja tidak ada di sini: baru diisi
     * setelah pengajuan disetujui admin (`saveProfile()`).
     *
     * @var array<int, array{0: string, 1: string, 2: array<int, string>}>
     */
    public const STEPS = [
        1 => ['Alamat Asal Pengiriman', 'Tempat paketmu dikirim. Dipakai untuk menghitung ongkir ke pembeli.', ['street', 'city', 'province', 'postalCode']],
        2 => ['Identitas Pemilik', 'Hanya dilihat admin untuk verifikasi. NIK disimpan terenkripsi dan foto KTP tidak bisa dibuka publik.', ['identityName', 'identityNumber', 'identityCard']],
        3 => ['Rekening Pencairan Dana', 'Hasil penjualan dicairkan ke rekening ini setelah pesanan selesai.', ['bankCode', 'accountName', 'accountNumber', 'agreed']],
    ];

    /** 0 = halaman perkenalan, 1–3 = langkah di `STEPS`. Hanya diubah lewat action. */
    #[Locked]
    public int $step = 0;

    public string $street = '';

    public string $city = '';

    public string $province = '';

    public string $postalCode = '';

    public string $identityName = '';

    public string $identityNumber = '';

    /** @var TemporaryUploadedFile|null */
    public $identityCard = null;

    public string $bankCode = '';

    public string $accountName = '';

    public string $accountNumber = '';

    public bool $agreed = false;

    // Profil toko, diisi setelah pengajuan disetujui.

    public string $name = '';

    public string $description = '';

    public string $phoneNumber = '';

    public string $email = '';

    /** @var TemporaryUploadedFile|null */
    public $logo = null;

    public function mount(): void
    {
        $user = $this->user;

        if ($user === null) {
            return;
        }

        $store = $this->store;

        $this->email = $user->email;
        $this->phoneNumber = (string) $user->phone_number;
        $this->identityName = $user->name;

        // Pengajuan yang ditolak dibuka lagi dengan isian lamanya supaya cukup diperbaiki.
        if ($store) {
            $this->fill([
                'street' => (string) $store->street,
                'city' => (string) $store->city,
                'province' => (string) $store->province,
                'postalCode' => (string) $store->postal_code,
                'identityName' => (string) $store->identity_name,
                'identityNumber' => (string) $store->identity_number,
                'name' => (string) $store->name,
                'description' => (string) $store->description,
                'phoneNumber' => (string) ($store->phone_number ?? $user->phone_number),
                'email' => $store->email ?? $user->email,
            ]);
        }

        if ($account = $user->payoutAccounts()->where('is_default', true)->first()) {
            $this->bankCode = (string) $account->bank_code;
            $this->accountName = $account->account_name;
            $this->accountNumber = $account->account_number;
        }

        // Yang ditolak sudah pernah membaca perkenalan; `?mulai=1` dari tombol "Mulai Daftar" sebelum login.
        if ($this->showsForm && ($store?->status === 'rejected' || request()->boolean('mulai'))) {
            $this->step = 1;
        }
    }

    #[Computed]
    public function user(): ?User
    {
        return Auth::user();
    }

    #[Computed]
    public function store(): ?Store
    {
        return $this->user?->store;
    }

    /**
     * Form hanya untuk pembeli yang belum punya toko atau pengajuannya ditolak.
     */
    #[Computed]
    public function showsForm(): bool
    {
        return (bool) $this->user?->canApplyForStore()
            && in_array($this->store?->status, [null, 'rejected'], true);
    }

    /**
     * Pengajuan sudah disetujui, tinggal profil tokonya yang belum diisi.
     */
    #[Computed]
    public function showsProfileForm(): bool
    {
        return $this->user?->role === 'seller' && (bool) $this->store?->needsProfile();
    }

    /**
     * Komisi platform untuk ditampilkan, mis. "5" atau "2,5".
     */
    #[Computed]
    public function feePercent(): string
    {
        return rtrim(rtrim(number_format(StoreOrder::PLATFORM_FEE_RATE * 100, 2, ',', ''), '0'), ',');
    }

    /**
     * Isi halaman perkenalan. Angkanya dibaca dari konstanta model supaya ikut
     * berubah kalau aturannya berubah.
     *
     * @return array<string, string> Judul => penjelasan.
     */
    #[Computed]
    public function benefits(): array
    {
        return [
            'Pembeli sudah tahu ukurannya' => 'Semua toko memakai standar ukuran FitMate yang sama dan pembeli menyimpan ukuran badannya di profil, jadi risiko salah ukuran dan retur lebih kecil.',
            'Hanya pesanan yang sudah lunas' => 'Pembeli membayar dulu lewat virtual account atau QRIS. Kamu baru mengemas pesanan setelah pembayarannya masuk, tanpa pesanan fiktif.',
            'Dana dijamin dan cair otomatis' => 'Pembayaran ditahan FitMate lalu masuk ke saldo tokomu setelah pembeli menerima paket, atau otomatis '.EscrowHold::AUTO_RELEASE_DAYS.' hari setelah paket sampai.',
            'Daftar gratis, tanpa biaya bulanan' => "FitMate hanya memotong komisi {$this->feePercent}% dari total paket yang terjual. Tidak laku, tidak bayar.",
            'Halaman toko sendiri' => 'Tokomu punya alamat sendiri lengkap dengan logo dan deskripsi, dan produkmu ikut tampil di katalog serta pencarian FitMate.',
            'Semua dari satu dashboard' => 'Pantau pesanan baru, isi nomor resi, dan lihat pendapatan serta dana yang masih ditahan dari dashboard seller.',
        ];
    }

    /**
     * @return array<string, string> Pertanyaan => jawaban.
     */
    #[Computed]
    public function questions(): array
    {
        return [
            'Berapa lama pengajuanku ditinjau?' => 'Biasanya 1–2 hari kerja. Kalau ditolak, alasannya muncul di halaman ini dan kamu bisa memperbaiki datanya lalu mengirim ulang.',
            'Kapan aku mengisi nama dan profil toko?' => 'Setelah pengajuanmu disetujui. Pendaftaran cukup berisi data yang perlu diverifikasi admin: alamat, KTP, dan rekening.',
            'Apakah data KTP-ku aman?' => 'NIK disimpan terenkripsi dan foto KTP ada di penyimpanan privat yang hanya bisa dibuka admin untuk verifikasi.',
            'Apakah aku masih bisa belanja?' => 'Bisa. Akun yang sama tetap dipakai untuk belanja, hanya saja kamu tidak bisa membeli produk dari tokomu sendiri.',
        ];
    }

    /**
     * Tombol "Mulai Daftar" di halaman perkenalan. Tamu diminta masuk dulu lalu
     * dikembalikan langsung ke langkah pertama.
     */
    public function start(): void
    {
        if ($this->user === null) {
            redirect()->setIntendedUrl(route('stores.create', ['mulai' => 1]));
            $this->redirectRoute('login', navigate: true);

            return;
        }

        abort_unless($this->showsForm, 403);

        $this->goTo(1);
    }

    /**
     * Validasi langkah yang sedang dibuka lalu pindah ke langkah berikutnya;
     * di langkah terakhir pengajuan langsung dikirim.
     */
    public function next(): void
    {
        abort_unless($this->showsForm && isset(self::STEPS[$this->step]), 403);

        if ($this->step === array_key_last(self::STEPS)) {
            $this->submit();

            return;
        }

        $this->validate($this->rulesForStep($this->step));

        $this->goTo($this->step + 1);
    }

    public function back(): void
    {
        $this->goTo(max(0, $this->step - 1));
    }

    /**
     * Lompat kembali ke langkah yang sudah dilewati lewat penanda langkah.
     */
    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step < $this->step) {
            $this->goTo($step);
        }
    }

    public function submit(): void
    {
        abort_unless($this->showsForm, 403);

        // Dicek per langkah supaya pembeli dibawa ke langkah yang masih salah.
        foreach (array_keys(self::STEPS) as $step) {
            try {
                $this->validate($this->rulesForStep($step));
            } catch (ValidationException $exception) {
                $this->step = $step;

                throw $exception;
            }
        }

        $user = $this->user;
        $store = $this->store ?? new Store(['user_id' => $user->id]);

        DB::transaction(function () use ($user, $store): void {
            $store->fill([
                'street' => $this->street,
                'city' => $this->city,
                'province' => $this->province,
                'postal_code' => $this->postalCode,
                'identity_name' => $this->identityName,
                'identity_number' => $this->identityNumber,
                'status' => 'pending',
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]);

            if ($this->identityCard) {
                $previous = $store->identity_card_path;
                $store->identity_card_path = $this->identityCard->store('identity-cards', Store::IDENTITY_DISK);

                if ($previous) {
                    Storage::disk(Store::IDENTITY_DISK)->delete($previous);
                }
            }

            $store->save();
            $user->setRelation('store', $store);

            // Rekening pencairan dicek ulang oleh admin, jadi selalu kembali belum terverifikasi.
            $user->payoutAccounts()->updateOrCreate(
                ['type' => 'bank', 'is_default' => true],
                [
                    'bank_code' => $this->bankCode,
                    'account_name' => $this->accountName,
                    'account_number' => $this->accountNumber,
                    'verified_at' => null,
                ],
            );
        });

        $this->reset('identityCard', 'agreed', 'step');
        unset($this->store, $this->showsForm);

        $this->dispatch('notify', message: 'Pengajuan toko terkirim. Admin akan meninjaunya.');
        $this->js('window.scrollTo({ top: 0, behavior: "smooth" })');
    }

    /**
     * Langkah terakhir setelah disetujui: nama, deskripsi, kontak, dan logo.
     * Setelah tersimpan toko punya slug dan mulai tampil di katalog.
     */
    public function saveProfile(): void
    {
        abort_unless($this->showsProfileForm, 403);

        $this->validate($this->profileRules());

        $store = $this->store;

        $store->fill([
            'name' => $this->name,
            'slug' => Store::uniqueSlug($this->name, $store->id),
            'description' => $this->description,
            'phone_number' => $this->phoneNumber,
            'email' => $this->email,
        ]);

        if ($this->logo) {
            $previous = $store->logo;
            $store->logo = $this->logo->store('stores/logos', 'public');

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        }

        $store->save();

        $this->reset('logo');
        unset($this->showsProfileForm);

        $this->dispatch('notify', message: "Profil {$store->name} tersimpan. Tokomu sekarang tampil di katalog.");
    }

    private function goTo(int $step): void
    {
        $this->step = $step;
        $this->resetErrorBag();
        $this->js('window.scrollTo({ top: 0, behavior: "smooth" })');
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForStep(int $step): array
    {
        $hasIdentityCard = (bool) $this->store?->identity_card_path;

        $rules = [
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postalCode' => ['required', 'digits:5'],
            'identityName' => ['required', 'string', 'max:255'],
            'identityNumber' => ['required', 'digits:16'],
            'identityCard' => [$hasIdentityCard ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'bankCode' => ['required', Rule::in(array_keys(self::BANKS))],
            'accountName' => ['required', 'string', 'max:255'],
            'accountNumber' => ['required', 'digits_between:6,20'],
            'agreed' => ['accepted'],
        ];

        return Arr::only($rules, self::STEPS[$step][2]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profileRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:60', Rule::unique('stores', 'name')->ignore($this->store?->id)],
            'description' => ['required', 'string', 'min:30', 'max:1000'],
            'phoneNumber' => ['required', 'string', 'regex:/^(\+62|0)8[0-9]{7,12}$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'phoneNumber.regex' => 'Masukkan nomor HP Indonesia, mis. 081234567890.',
            'identityNumber.digits' => 'NIK harus 16 angka.',
            'identityCard.required' => 'Unggah foto KTP untuk verifikasi.',
            'agreed.accepted' => 'Centang persetujuan sebelum mengirim.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'store name',
            'phoneNumber' => 'phone number',
            'postalCode' => 'postal code',
            'identityName' => 'name on ID card',
            'identityNumber' => 'NIK',
            'identityCard' => 'ID card photo',
            'bankCode' => 'bank',
            'accountName' => 'account holder name',
            'accountNumber' => 'account number',
        ];
    }
};
?>

<div>
    @php
        $store = $this->store;
    @endphp

    @if ($this->user === null || ($this->showsForm && $step === 0))
        {{-- Perkenalan program seller --}}
        <section class="mx-auto max-w-[1120px] px-side pb-[clamp(40px,7vw,72px)] pt-[clamp(32px,6vw,56px)]">
            <x-ui.breadcrumbs :items="['Beranda' => route('home'), 'Buka Toko' => null]" />

            <div class="mt-[clamp(24px,5vw,48px)] grid items-center gap-[clamp(28px,5vw,56px)] lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">FitMate untuk Penjual</p>
                    <h1 class="mt-3 max-w-[16ch] font-display text-[clamp(2.1rem,5vw,3.4rem)] font-normal leading-[1.08]">Jual koleksimu ke pembeli yang sudah tahu ukurannya.</h1>
                    <p class="mt-4 max-w-[52ch] text-[clamp(0.95rem,1.6vw,1.05rem)] leading-relaxed text-muted">
                        Buka toko di FitMate tanpa biaya pendaftaran. Kami mengurus pembayaran dan menjamin danamu,
                        kamu fokus pada koleksi dan pengiriman.
                    </p>

                    <div class="mt-7 flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            wire:click="start"
                            class="inline-flex h-12 cursor-pointer items-center justify-center gap-2 rounded-[2px] bg-ink px-7 text-[12px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity duration-200 hover:opacity-85"
                        >
                            {{ $this->user ? 'Mulai Daftar' : 'Masuk & Mulai Daftar' }}
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                        </button>
                        <a href="#cara-kerja" class="inline-flex h-12 items-center rounded-[2px] border border-line px-6 text-[12px] font-bold uppercase tracking-[0.1em] transition-colors hover:border-ink">
                            Cara Kerjanya
                        </a>
                    </div>

                    @guest
                        <p class="mt-3 text-[12.5px] text-muted">
                            Belum punya akun? <a href="{{ route('register') }}" wire:navigate class="font-semibold text-ink underline-offset-4 hover:underline">Daftar dulu sebagai pembeli</a>, lalu ajukan tokomu.
                        </p>
                    @endguest
                </div>

                <dl class="grid grid-cols-1 divide-y divide-line rounded-md border border-line bg-surface sm:grid-cols-3 sm:divide-x sm:divide-y-0 lg:grid-cols-1 lg:divide-x-0 lg:divide-y">
                    <div class="px-[clamp(18px,3vw,26px)] py-5">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Biaya pendaftaran</dt>
                        <dd class="mt-1.5 font-display text-[2rem] leading-none">Rp 0</dd>
                        <dd class="mt-1.5 text-[12.5px] text-muted">Tanpa iuran bulanan.</dd>
                    </div>
                    <div class="px-[clamp(18px,3vw,26px)] py-5">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Komisi</dt>
                        <dd class="mt-1.5 font-display text-[2rem] leading-none">{{ $this->feePercent }}%</dd>
                        <dd class="mt-1.5 text-[12.5px] text-muted">Hanya dari paket yang terjual.</dd>
                    </div>
                    <div class="px-[clamp(18px,3vw,26px)] py-5">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Dana cair otomatis</dt>
                        <dd class="mt-1.5 font-display text-[2rem] leading-none">{{ EscrowHold::AUTO_RELEASE_DAYS }} hari</dd>
                        <dd class="mt-1.5 text-[12.5px] text-muted">Setelah paket sampai ke pembeli.</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="border-y border-line bg-surface">
            <div class="mx-auto max-w-[1120px] px-side py-[clamp(40px,7vw,72px)]">
                <h2 class="font-display text-[clamp(1.5rem,3.2vw,2rem)] font-normal leading-tight">Kenapa berjualan di FitMate</h2>
                <p class="mt-1.5 max-w-[60ch] text-[13.5px] text-muted">Marketplace fesyen yang dibangun di atas ukuran yang pas dan pembayaran yang aman.</p>

                <ul class="mt-8 grid gap-px overflow-hidden rounded-md border border-line bg-line sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->benefits as $title => $text)
                        <li wire:key="benefit-{{ $loop->index }}" class="bg-surface px-[clamp(18px,3vw,26px)] py-6">
                            <span class="flex size-9 items-center justify-center rounded-full border border-ink text-[12px] font-bold" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span>
                            <h3 class="mt-4 text-[14px] font-semibold">{{ $title }}</h3>
                            <p class="mt-1.5 text-[13px] leading-relaxed text-muted">{{ $text }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section id="cara-kerja" class="mx-auto max-w-[1120px] scroll-mt-[calc(var(--spacing-header)+16px)] px-side py-[clamp(40px,7vw,72px)]">
            <h2 class="font-display text-[clamp(1.5rem,3.2vw,2rem)] font-normal leading-tight">Cara kerjanya</h2>
            <p class="mt-1.5 text-[13.5px] text-muted">Dari daftar sampai pesanan pertama, dalam empat langkah.</p>

            <ol class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['Isi formulir', 'Alamat asal pengiriman, foto KTP, dan rekening — diisi bertahap, sekitar 5 menit.'],
                    ['Ditinjau admin', 'Admin mencocokkan KTP dan rekeningmu, biasanya dalam 1–2 hari kerja.'],
                    ['Lengkapi profil toko', 'Setelah disetujui, beri nama, deskripsi, kontak, dan logo tokomu.'],
                    ['Mulai berjualan', 'Unggah produk, terima pesanan yang sudah dibayar, lalu kirim.'],
                ] as [$title, $text])
                    <li wire:key="journey-{{ $loop->index }}" class="rounded-md border border-line bg-surface px-[18px] py-5">
                        <span class="flex size-7 items-center justify-center rounded-full bg-ink text-[12px] font-bold text-cream">{{ $loop->iteration }}</span>
                        <h3 class="mt-3.5 text-[13.5px] font-semibold">{{ $title }}</h3>
                        <p class="mt-1 text-[12.5px] leading-relaxed text-muted">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>

            <div class="mt-10 grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <section class="h-fit rounded-md border border-line bg-surface px-[clamp(18px,3vw,26px)] py-6">
                    <h3 class="text-[12px] font-semibold uppercase tracking-[0.1em]">Yang perlu disiapkan</h3>

                    <ul class="mt-4 flex flex-col gap-3.5 text-[13px]">
                        @foreach ([
                            'Akun pembeli FitMate' => 'Toko diajukan dari akunmu sendiri.',
                            'Foto KTP' => 'Jelas, tidak buram, nama dan NIK terbaca.',
                            'Rekening bank' => 'Sebaiknya atas nama sendiri: '.collect($this::BANKS)->join(', ', ', atau ').'.',
                            'Alamat asal pengiriman' => 'Tempat paket diambil kurir.',
                        ] as $item => $detail)
                            <li wire:key="requirement-{{ $loop->index }}" class="flex gap-3">
                                <svg class="mt-0.5 size-4 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                                <span>
                                    <span class="font-semibold">{{ $item }}</span>
                                    <span class="block text-[12.5px] text-muted">{{ $detail }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="rounded-md border border-line bg-surface">
                    <h3 class="border-b border-line px-[clamp(18px,3vw,26px)] py-4 text-[12px] font-semibold uppercase tracking-[0.1em]">Pertanyaan Umum</h3>

                    <div class="divide-y divide-line">
                        @foreach ($this->questions as $question => $answer)
                            <details wire:key="faq-{{ $loop->index }}" class="group px-[clamp(18px,3vw,26px)]">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 text-[13.5px] font-medium [&::-webkit-details-marker]:hidden">
                                    {{ $question }}
                                    <svg class="size-4 shrink-0 text-muted transition-transform duration-200 group-open:rotate-45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                                        <path d="M12 5v14M5 12h14" />
                                    </svg>
                                </summary>
                                <p class="pb-4 text-[13px] leading-relaxed text-muted">{{ $answer }}</p>
                            </details>
                        @endforeach
                    </div>
                </section>
            </div>

            <div class="mt-10 flex flex-col items-start justify-between gap-5 rounded-md bg-ink px-[clamp(20px,4vw,40px)] py-[clamp(24px,4vw,36px)] text-cream sm:flex-row sm:items-center">
                <div>
                    <p class="font-display text-[clamp(1.4rem,3vw,1.9rem)] leading-tight">Siap membuka toko?</p>
                    <p class="mt-1 text-[13px] opacity-80">Pendaftaran gratis dan bisa dilanjutkan bertahap.</p>
                </div>
                <button
                    type="button"
                    wire:click="start"
                    class="inline-flex h-12 shrink-0 cursor-pointer items-center justify-center rounded-[2px] bg-cream px-7 text-[12px] font-bold uppercase tracking-[0.1em] text-ink transition-opacity duration-200 hover:opacity-85"
                >
                    {{ $this->user ? 'Mulai Daftar' : 'Masuk & Mulai Daftar' }}
                </button>
            </div>
        </section>
    @else
        <div class="mx-auto max-w-[1120px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
            <x-ui.area-header
                :breadcrumbs="['Beranda' => route('home'), 'Buka Toko' => null]"
                title="Buka Toko di FitMate"
                description="Jual koleksimu ke pembeli yang sudah tahu ukurannya. Setiap toko ditinjau admin sebelum mulai berjualan."
            />

            @if (! $this->user->canShop() || ($this->user->role === 'seller' && ! $store))
                {{-- Staf platform, atau akun seller lama yang belum dibuatkan toko --}}
                <div class="mt-6 rounded-md border border-line bg-surface px-[18px] py-12 text-center">
                    <p class="font-display text-xl">Akun ini tidak bisa mengajukan toko</p>
                    <p class="mt-1.5 text-[13px] text-muted">Gunakan akun pembeli untuk mengajukan toko, atau hubungi admin.</p>
                </div>
            @elseif ($this->showsProfileForm)
                {{-- Disetujui, tinggal profil toko --}}
                <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                    <form wire:submit="saveProfile" class="rounded-md border border-line bg-surface" novalidate>
                        <header class="border-b border-line px-[clamp(18px,3vw,28px)] py-5">
                            <span class="inline-flex rounded-[3px] bg-success/12 px-2 py-0.5 text-[11px] font-semibold text-success">Pengajuan disetujui</span>
                            <h2 class="mt-2.5 font-display text-[1.45rem] font-normal leading-tight">Lengkapi Profil Toko</h2>
                            <p class="mt-1 text-[12.5px] leading-relaxed text-muted">Nama, deskripsi, dan logo ini tampil di halaman tokomu dan di setiap produk. Tokomu muncul di katalog setelah profil ini disimpan.</p>
                        </header>

                        <div class="grid gap-4 px-[clamp(18px,3vw,28px)] py-6 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <x-ui.input name="name" label="Nama Toko" wire:model="name" :value="$name" maxlength="60" />
                            </div>

                            <div class="sm:col-span-2">
                                <label for="description" class="block text-[12px] font-semibold">Deskripsi Toko</label>
                                <textarea
                                    id="description"
                                    wire:model="description"
                                    rows="4"
                                    maxlength="1000"
                                    placeholder="Apa yang kamu jual, bahan, gaya, dan kenapa pembeli perlu memilih tokomu (min. 30 karakter)."
                                    @class([
                                        'mt-1.5 w-full rounded-[2px] border bg-bg px-3 py-2.5 text-[13px] outline-none transition-colors placeholder:text-muted focus:border-ink',
                                        'border-sale' => $errors->has('description'),
                                        'border-line' => ! $errors->has('description'),
                                    ])
                                >{{ $description }}</textarea>
                                @error('description') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                            </div>

                            <x-ui.input name="phoneNumber" label="No. HP Toko" type="tel" wire:model="phoneNumber" :value="$phoneNumber" placeholder="081234567890" />
                            <x-ui.input name="email" label="Email Toko" type="email" wire:model="email" :value="$email" />

                            <div class="sm:col-span-2">
                                <span class="block text-[12px] font-semibold">Logo Toko <span class="font-normal text-muted">(opsional)</span></span>
                                <div class="mt-1.5 flex items-center gap-3">
                                    <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-cream text-[11px] text-muted">
                                        @if ($logo && ! $errors->has('logo'))
                                            <img src="{{ $logo->temporaryUrl() }}" alt="Pratinjau logo" class="size-full object-cover">
                                        @elseif ($store->logoUrl())
                                            <img src="{{ $store->logoUrl() }}" alt="Logo toko" class="size-full object-cover">
                                        @else
                                            Logo
                                        @endif
                                    </span>
                                    <label class="inline-flex cursor-pointer items-center rounded-[2px] border border-line px-4 py-2 text-[12px] font-semibold uppercase tracking-[0.04em] transition-colors hover:border-ink">
                                        <span wire:loading.remove wire:target="logo">Pilih Logo</span>
                                        <span wire:loading wire:target="logo">Mengunggah…</span>
                                        <input type="file" wire:model="logo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                                    </label>
                                </div>
                                @error('logo') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <footer class="flex justify-end border-t border-line px-[clamp(18px,3vw,28px)] py-4">
                            <x-ui.button target="saveProfile" class="sm:w-auto sm:min-w-[220px]">Simpan &amp; Buka Toko</x-ui.button>
                        </footer>
                    </form>

                    <x-store.apply-steps :current="3" />
                </div>
            @elseif ($this->user->role === 'seller')
                {{-- Sudah disetujui dan profilnya lengkap --}}
                <div class="mt-6 rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-8">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">Status: {{ $store->statusLabel() }}</p>
                    <p class="mt-2 font-display text-2xl">{{ $store->displayName() }}</p>

                    @if ($store->status === 'suspended')
                        <p class="mt-3 rounded-[2px] bg-sale/10 px-3 py-2 text-[13px] text-sale">
                            Toko ditangguhkan admin{{ $store->review_note ? ': '.$store->review_note : '.' }} Produkmu tidak tampil di katalog sampai toko diaktifkan lagi.
                        </p>
                    @else
                        <p class="mt-2 text-[13px] text-muted">Tokomu sudah aktif dan bisa berjualan.</p>
                    @endif

                    <div class="mt-5 flex flex-wrap gap-2.5">
                        <a href="{{ route('seller.dashboard') }}" wire:navigate class="inline-flex items-center rounded-[2px] bg-ink px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity hover:opacity-85">Dashboard Toko</a>
                        @if ($store->canSell())
                            <a href="{{ route('stores.show', $store) }}" wire:navigate class="inline-flex items-center rounded-[2px] border border-line px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.1em] transition-colors hover:border-ink">Lihat Halaman Toko</a>
                        @endif
                    </div>
                </div>
            @elseif ($store?->status === 'pending')
                {{-- Menunggu review --}}
                <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                    <section class="rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-8">
                        <span class="inline-flex rounded-[3px] bg-cream px-2 py-0.5 text-[11px] font-semibold">Menunggu Review</span>
                        <h2 class="mt-3 font-display text-2xl font-normal">Pengajuanmu sedang ditinjau</h2>
                        <p class="mt-2 text-[13px] leading-relaxed text-muted">
                            Dikirim {{ $store->submitted_at?->format('d M Y, H:i') }}. Admin memeriksa KTP dan rekeningmu dalam 1&ndash;2 hari kerja.
                            Setelah disetujui, kamu tinggal melengkapi nama dan profil toko di halaman ini.
                        </p>

                        <dl class="mt-6 grid gap-x-6 gap-y-3 border-t border-line pt-5 text-[12.5px] sm:grid-cols-2">
                            <div class="sm:col-span-2"><dt class="text-muted">Alamat asal pengiriman</dt><dd class="font-medium">{{ collect([$store->street, $store->city, $store->province, $store->postal_code])->filter()->join(', ') }}</dd></div>
                            <div><dt class="text-muted">Pemilik (sesuai KTP)</dt><dd class="font-medium">{{ $store->identity_name }}</dd></div>
                            <div><dt class="text-muted">Rekening</dt><dd class="font-medium">{{ $this::BANKS[$bankCode] ?? strtoupper($bankCode) }} &middot; {{ $accountNumber }}</dd></div>
                        </dl>
                    </section>

                    <x-store.apply-steps :current="2" />
                </div>
            @elseif ($this->showsForm)
                @php
                    [$stepTitle, $stepDescription] = $this::STEPS[$step];
                    $isLastStep = $step === array_key_last($this::STEPS);
                @endphp

                <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                    <div class="flex flex-col gap-5">
                        @if ($store?->status === 'rejected')
                            <div class="rounded-md border border-sale/30 bg-sale/5 px-[18px] py-4" role="alert">
                                <p class="text-[13.5px] font-semibold text-sale">Pengajuan sebelumnya ditolak</p>
                                <p class="mt-1 text-[12.5px] text-muted">Alasan admin: <span class="text-ink">{{ $store->review_note }}</span></p>
                                <p class="mt-1 text-[12.5px] text-muted">Perbaiki datanya lalu kirim ulang.</p>
                            </div>
                        @endif

                        <form wire:submit="next" class="rounded-md border border-line bg-surface" novalidate>
                            {{-- Penanda langkah --}}
                            <ol class="grid grid-cols-3 border-b border-line">
                                @foreach ($this::STEPS as $number => [$title])
                                    <li wire:key="step-{{ $number }}" @class(['border-l border-line' => ! $loop->first])>
                                        <button
                                            type="button"
                                            wire:click="goToStep({{ $number }})"
                                            @disabled($number >= $step)
                                            @if ($number === $step) aria-current="step" @endif
                                            @class([
                                                'flex w-full items-center gap-2.5 px-[clamp(10px,2vw,18px)] py-3.5 text-left',
                                                'cursor-pointer hover:bg-cream' => $number < $step,
                                                'cursor-default' => $number >= $step,
                                            ])
                                        >
                                            <span @class([
                                                'flex size-6 shrink-0 items-center justify-center rounded-full border text-[11px] font-bold',
                                                'border-ink bg-ink text-cream' => $number <= $step,
                                                'border-line text-muted' => $number > $step,
                                            ])>
                                                @if ($number < $step)
                                                    <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                                                    <span class="sr-only">Selesai:</span>
                                                @else
                                                    {{ $number }}
                                                @endif
                                            </span>
                                            <span @class(['hidden text-[12px] font-semibold leading-tight sm:block', 'text-muted' => $number > $step])>{{ $title }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ol>

                            <header class="px-[clamp(18px,3vw,28px)] pt-6">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Langkah {{ $step }} dari {{ count($this::STEPS) }}</p>
                                <h2 class="mt-1.5 font-display text-[1.45rem] font-normal leading-tight">{{ $stepTitle }}</h2>
                                <p class="mt-1 text-[12.5px] leading-relaxed text-muted">{{ $stepDescription }}</p>
                            </header>

                            <div class="grid gap-4 px-[clamp(18px,3vw,28px)] py-6 sm:grid-cols-2">
                                @if ($step === 1)
                                    <div class="sm:col-span-2">
                                        <x-ui.input name="street" label="Alamat Lengkap" wire:model="street" :value="$street" placeholder="Jalan, nomor, RT/RW, kelurahan" />
                                    </div>
                                    <x-ui.input name="city" label="Kota / Kabupaten" wire:model="city" :value="$city" />
                                    <x-ui.input name="province" label="Provinsi" wire:model="province" :value="$province" />
                                    <x-ui.input name="postalCode" label="Kode Pos" inputmode="numeric" maxlength="5" wire:model="postalCode" :value="$postalCode" />
                                @elseif ($step === 2)
                                    <x-ui.input name="identityName" label="Nama Sesuai KTP" wire:model="identityName" :value="$identityName" />
                                    <x-ui.input name="identityNumber" label="NIK" inputmode="numeric" maxlength="16" wire:model="identityNumber" :value="$identityNumber" autocomplete="off" />

                                    <div class="sm:col-span-2">
                                        <span class="block text-[12px] font-semibold">Foto KTP</span>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-3">
                                            @if ($identityCard && ! $errors->has('identityCard'))
                                                <img src="{{ $identityCard->temporaryUrl() }}" alt="Pratinjau KTP" class="h-20 w-32 rounded-[2px] border border-line object-cover">
                                            @elseif ($store?->identity_card_path)
                                                <span class="rounded-[2px] bg-cream px-3 py-2 text-[12px] text-muted">KTP sudah diunggah — pilih lagi hanya kalau mau mengganti.</span>
                                            @endif

                                            <label class="inline-flex cursor-pointer items-center rounded-[2px] border border-line px-4 py-2 text-[12px] font-semibold uppercase tracking-[0.04em] transition-colors hover:border-ink">
                                                <span wire:loading.remove wire:target="identityCard">{{ $identityCard || $store?->identity_card_path ? 'Ganti Foto KTP' : 'Unggah Foto KTP' }}</span>
                                                <span wire:loading wire:target="identityCard">Mengunggah…</span>
                                                <input type="file" wire:model="identityCard" accept="image/jpeg,image/png,image/webp" class="sr-only">
                                            </label>
                                        </div>
                                        <p class="mt-1 text-[11.5px] text-muted">JPG, PNG, atau WebP, maks. 4 MB. Pastikan tulisan terbaca jelas.</p>
                                        @error('identityCard') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                                    </div>
                                @else
                                    <div>
                                        <label for="bankCode" class="block text-[12px] font-semibold">Bank</label>
                                        <select
                                            id="bankCode"
                                            wire:model="bankCode"
                                            @class([
                                                'mt-1.5 h-11 w-full rounded-[2px] border bg-bg px-3 text-[13px] outline-none transition-colors focus:border-ink',
                                                'border-sale' => $errors->has('bankCode'),
                                                'border-line' => ! $errors->has('bankCode'),
                                            ])
                                        >
                                            <option value="" @selected($bankCode === '')>Pilih bank</option>
                                            @foreach ($this::BANKS as $code => $bank)
                                                <option value="{{ $code }}" @selected($bankCode === $code)>{{ $bank }}</option>
                                            @endforeach
                                        </select>
                                        @error('bankCode') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                                    </div>
                                    <x-ui.input name="accountNumber" label="No. Rekening" inputmode="numeric" wire:model="accountNumber" :value="$accountNumber" />
                                    <div class="sm:col-span-2">
                                        <x-ui.input name="accountName" label="Nama Pemilik Rekening" wire:model="accountName" :value="$accountName" hint="Sebaiknya sama dengan nama di KTP supaya verifikasi lebih cepat." />
                                    </div>

                                    <div class="border-t border-line pt-4 sm:col-span-2">
                                        <label class="flex cursor-pointer items-start gap-2.5 text-[13px]">
                                            <input type="checkbox" wire:model="agreed" @checked($agreed) class="mt-0.5 size-4 shrink-0 accent-ink">
                                            <span>Data yang saya isi benar, dan saya setuju dana pembeli ditahan FitMate sampai pesanan selesai sebelum dicairkan ke rekening saya.</span>
                                        </label>
                                        @error('agreed') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                            </div>

                            <footer class="flex items-center justify-between gap-3 border-t border-line px-[clamp(18px,3vw,28px)] py-4">
                                <button type="button" wire:click="back" class="inline-flex h-11 cursor-pointer items-center gap-1.5 rounded-[2px] px-2 text-[11.5px] font-bold uppercase tracking-[0.1em] text-muted transition-colors hover:text-ink">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6" /></svg>
                                    Kembali
                                </button>

                                <x-ui.button target="next" class="sm:w-auto sm:min-w-[220px]">
                                    @if ($isLastStep)
                                        {{ $store?->status === 'rejected' ? 'Kirim Ulang Pengajuan' : 'Kirim Pengajuan' }}
                                    @else
                                        Lanjut
                                    @endif
                                </x-ui.button>
                            </footer>
                        </form>
                    </div>

                    <x-store.apply-steps :current="1" />
                </div>
            @endif
        </div>
    @endif
</div>
