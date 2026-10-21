<?php

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
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

    public string $name = '';

    public string $description = '';

    public string $phoneNumber = '';

    public string $email = '';

    public string $street = '';

    public string $city = '';

    public string $province = '';

    public string $postalCode = '';

    public string $identityName = '';

    public string $identityNumber = '';

    /** @var TemporaryUploadedFile|null */
    public $identityCard = null;

    /** @var TemporaryUploadedFile|null */
    public $logo = null;

    public string $bankCode = '';

    public string $accountName = '';

    public string $accountNumber = '';

    public bool $agreed = false;

    public function mount(): void
    {
        $user = $this->user;
        $store = $this->store;

        $this->email = $user->email;
        $this->phoneNumber = (string) $user->phone_number;
        $this->identityName = $user->name;

        // Pengajuan yang ditolak dibuka lagi dengan isian lamanya supaya cukup diperbaiki.
        if ($store) {
            $this->fill([
                'name' => $store->name,
                'description' => (string) $store->description,
                'phoneNumber' => (string) $store->phone_number,
                'email' => (string) $store->email,
                'street' => (string) $store->street,
                'city' => (string) $store->city,
                'province' => (string) $store->province,
                'postalCode' => (string) $store->postal_code,
                'identityName' => (string) $store->identity_name,
                'identityNumber' => (string) $store->identity_number,
            ]);
        }

        if ($account = $user->payoutAccounts()->where('is_default', true)->first()) {
            $this->bankCode = (string) $account->bank_code;
            $this->accountName = $account->account_name;
            $this->accountNumber = $account->account_number;
        }
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function store(): ?Store
    {
        return $this->user->store;
    }

    /**
     * Form hanya untuk pembeli yang belum punya toko atau pengajuannya ditolak.
     */
    #[Computed]
    public function showsForm(): bool
    {
        return $this->user->canApplyForStore()
            && in_array($this->store?->status, [null, 'rejected'], true);
    }

    public function submit(): void
    {
        abort_unless($this->showsForm, 403);

        $this->validate();

        $user = $this->user;
        $store = $this->store ?? new Store(['user_id' => $user->id]);

        DB::transaction(function () use ($user, $store): void {
            $store->fill([
                'name' => $this->name,
                'slug' => Store::uniqueSlug($this->name, $store->id),
                'description' => $this->description,
                'phone_number' => $this->phoneNumber,
                'email' => $this->email,
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

            if ($this->logo) {
                $previous = $store->logo;
                $store->logo = $this->logo->store('stores/logos', 'public');

                if ($previous) {
                    Storage::disk('public')->delete($previous);
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

        $this->reset('identityCard', 'logo', 'agreed');
        unset($this->store, $this->showsForm);

        $this->dispatch('notify', message: 'Pengajuan toko terkirim. Admin akan meninjaunya.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $hasIdentityCard = (bool) $this->store?->identity_card_path;

        return [
            'name' => ['required', 'string', 'min:3', 'max:60', Rule::unique('stores', 'name')->ignore($this->store?->id)],
            'description' => ['required', 'string', 'min:30', 'max:1000'],
            'phoneNumber' => ['required', 'string', 'regex:/^(\+62|0)8[0-9]{7,12}$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postalCode' => ['required', 'digits:5'],
            'identityName' => ['required', 'string', 'max:255'],
            'identityNumber' => ['required', 'digits:16'],
            'identityCard' => [$hasIdentityCard ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'bankCode' => ['required', Rule::in(array_keys(self::BANKS))],
            'accountName' => ['required', 'string', 'max:255'],
            'accountNumber' => ['required', 'digits_between:6,20'],
            'agreed' => ['accepted'],
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

<div class="mx-auto max-w-[1120px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Buka Toko' => null]"
        title="Buka Toko di FitMate"
        description="Jual koleksimu ke pembeli yang sudah tahu ukurannya. Setiap toko ditinjau admin sebelum mulai berjualan."
    />

    @php
        $store = $this->store;
    @endphp

    @if (! $this->user->canShop() || ($this->user->role === 'seller' && ! $store))
        {{-- Staf platform, atau akun seller lama yang belum dibuatkan toko --}}
        <div class="mt-6 rounded-md border border-line bg-surface px-[18px] py-12 text-center">
            <p class="font-display text-xl">Akun ini tidak bisa mengajukan toko</p>
            <p class="mt-1.5 text-[13px] text-muted">Gunakan akun pembeli untuk mengajukan toko, atau hubungi admin.</p>
        </div>
    @elseif ($this->user->role === 'seller')
        {{-- Sudah disetujui --}}
        <div class="mt-6 rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-8">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">Status: {{ $store->statusLabel() }}</p>
            <p class="mt-2 font-display text-2xl">{{ $store->name }}</p>

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
                <p class="mt-3 font-display text-2xl">{{ $store->name }}</p>
                <p class="mt-2 text-[13px] leading-relaxed text-muted">
                    Pengajuanmu dikirim {{ $store->submitted_at?->format('d M Y, H:i') }}. Admin memeriksa data toko, KTP,
                    dan rekening dalam 1&ndash;2 hari kerja. Status di halaman ini akan berubah begitu ada keputusan.
                </p>

                <dl class="mt-6 grid gap-x-6 gap-y-3 border-t border-line pt-5 text-[12.5px] sm:grid-cols-2">
                    <div><dt class="text-muted">Kontak</dt><dd class="font-medium">{{ $store->phone_number }} &middot; {{ $store->email }}</dd></div>
                    <div><dt class="text-muted">Alamat pengiriman</dt><dd class="font-medium">{{ $store->city }}, {{ $store->province }}</dd></div>
                    <div><dt class="text-muted">Pemilik (sesuai KTP)</dt><dd class="font-medium">{{ $store->identity_name }}</dd></div>
                    <div><dt class="text-muted">Rekening</dt><dd class="font-medium">{{ $this::BANKS[$bankCode] ?? strtoupper($bankCode) }} &middot; {{ $accountNumber }}</dd></div>
                </dl>
            </section>

            <x-store.apply-steps :current="2" />
        </div>
    @elseif ($this->showsForm)
        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
            <form wire:submit="submit" class="flex flex-col gap-5" novalidate>
                @if ($store?->status === 'rejected')
                    <div class="rounded-md border border-sale/30 bg-sale/5 px-[18px] py-4" role="alert">
                        <p class="text-[13.5px] font-semibold text-sale">Pengajuan sebelumnya ditolak</p>
                        <p class="mt-1 text-[12.5px] text-muted">Alasan admin: <span class="text-ink">{{ $store->review_note }}</span></p>
                        <p class="mt-1 text-[12.5px] text-muted">Perbaiki datanya lalu kirim ulang.</p>
                    </div>
                @endif

                <fieldset class="rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-6">
                    <legend class="px-1 text-[12px] font-semibold uppercase tracking-[0.1em]">1. Profil Toko</legend>

                    <div class="mt-2 grid gap-4 sm:grid-cols-2">
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
                                    @elseif ($store?->logo)
                                        <img src="{{ asset('storage/'.$store->logo) }}" alt="Logo toko" class="size-full object-cover">
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
                </fieldset>

                <fieldset class="rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-6">
                    <legend class="px-1 text-[12px] font-semibold uppercase tracking-[0.1em]">2. Alamat Asal Pengiriman</legend>

                    <div class="mt-2 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <x-ui.input name="street" label="Alamat Lengkap" wire:model="street" :value="$street" placeholder="Jalan, nomor, RT/RW, kelurahan" />
                        </div>
                        <x-ui.input name="city" label="Kota / Kabupaten" wire:model="city" :value="$city" />
                        <x-ui.input name="province" label="Provinsi" wire:model="province" :value="$province" />
                        <x-ui.input name="postalCode" label="Kode Pos" inputmode="numeric" maxlength="5" wire:model="postalCode" :value="$postalCode" />
                    </div>
                </fieldset>

                <fieldset class="rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-6">
                    <legend class="px-1 text-[12px] font-semibold uppercase tracking-[0.1em]">3. Identitas Pemilik</legend>
                    <p class="mt-1 text-[12px] text-muted">Hanya dilihat admin untuk verifikasi. NIK disimpan terenkripsi dan foto KTP tidak bisa dibuka publik.</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
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
                                    <span wire:loading.remove wire:target="identityCard">{{ $store?->identity_card_path ? 'Ganti Foto KTP' : 'Unggah Foto KTP' }}</span>
                                    <span wire:loading wire:target="identityCard">Mengunggah…</span>
                                    <input type="file" wire:model="identityCard" accept="image/jpeg,image/png,image/webp" class="sr-only">
                                </label>
                            </div>
                            <p class="mt-1 text-[11.5px] text-muted">JPG, PNG, atau WebP, maks. 4 MB. Pastikan tulisan terbaca jelas.</p>
                            @error('identityCard') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-6">
                    <legend class="px-1 text-[12px] font-semibold uppercase tracking-[0.1em]">4. Rekening Pencairan Dana</legend>
                    <p class="mt-1 text-[12px] text-muted">Hasil penjualan dicairkan ke rekening ini setelah pesanan selesai.</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
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
                    </div>
                </fieldset>

                <div class="rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-5">
                    <label class="flex cursor-pointer items-start gap-2.5 text-[13px]">
                        <input type="checkbox" wire:model="agreed" class="mt-0.5 size-4 shrink-0 accent-ink">
                        <span>Data yang saya isi benar, dan saya setuju dana pembeli ditahan FitMate sampai pesanan selesai sebelum dicairkan ke rekening saya.</span>
                    </label>
                    @error('agreed') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror

                    <x-ui.button target="submit" class="mt-5 sm:w-auto sm:min-w-[220px]">
                        {{ $store?->status === 'rejected' ? 'Kirim Ulang Pengajuan' : 'Kirim Pengajuan' }}
                    </x-ui.button>
                </div>
            </form>

            <x-store.apply-steps :current="1" />
        </div>
    @endif
</div>
