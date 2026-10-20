<?php

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Profil Saya')] class extends Component
{
    use WithFileUploads;

    /**
     * Ukuran badan yang bisa diisi pembeli: kolom `user_body_profiles` =>
     * [label, satuan, minimum, maksimum].
     *
     * @var array<string, array{0: string, 1: string, 2: int, 3: int}>
     */
    public const MEASUREMENTS = [
        'height' => ['Tinggi Badan', 'cm', 80, 250],
        'weight' => ['Berat Badan', 'kg', 20, 300],
        'chest' => ['Lingkar Dada', 'cm', 40, 200],
        'waist' => ['Lingkar Pinggang', 'cm', 40, 200],
        'hip' => ['Lingkar Pinggul', 'cm', 40, 200],
        'foot_length' => ['Panjang Telapak Kaki', 'cm', 10, 40],
    ];

    /**
     * Field alamat utama: kolom `user_addresses` => label.
     *
     * @var array<string, string>
     */
    public const ADDRESS_FIELDS = [
        'label' => 'Label Alamat',
        'recipient_name' => 'Nama Penerima',
        'recipient_phone' => 'No. Telepon Penerima',
        'street' => 'Alamat Lengkap',
        'city' => 'Kota / Kabupaten',
        'state' => 'Provinsi',
        'postal_code' => 'Kode Pos',
        'country' => 'Negara',
    ];

    public const THEMES = User::THEMES;

    /**
     * Ikon tiap pilihan tema (path SVG 24x24); kuncinya sama dengan `User::THEMES`.
     *
     * @var array<string, string>
     */
    public const THEME_ICONS = [
        'light' => 'M12 3v2M12 19v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M3 12h2M19 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
        'dark' => 'M20.5 14.1A8.5 8.5 0 0 1 9.9 3.5a8.5 8.5 0 1 0 10.6 10.6Z',
        'system' => 'M4 5h16v11H4zM9 20h6M12 16v4',
    ];

    #[Url]
    public string $tab = 'data';

    public string $name = '';

    public string $email = '';

    public string $phoneNumber = '';

    public string $gender = '';

    /** @var TemporaryUploadedFile|null Foto profil baru yang belum disimpan. */
    public $photo = null;

    public string $currentPassword = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $theme = 'system';

    /** @var array<string, string> */
    public array $measurements = [];

    /** @var array<string, string> */
    public array $address = [];

    public function mount(): void
    {
        // `tab` bisa diisi sembarangan lewat URL, termasuk tab khusus pembeli.
        if (! array_key_exists($this->tab, $this->tabs)) {
            $this->tab = 'data';
        }

        $user = $this->user;

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phoneNumber = (string) $user->phone_number;
        $this->gender = (string) $user->gender;
        $this->theme = User::themeFor($user);

        $bodyProfile = $user->defaultBodyProfile;

        $this->measurements = collect(self::MEASUREMENTS)
            ->mapWithKeys(fn (array $measurement, string $column): array => [
                $column => $this->formatMeasurement($bodyProfile?->{$column}),
            ])
            ->all();

        $address = $user->defaultAddress;

        $this->address = [
            'label' => $address->label ?? 'Rumah',
            'recipient_name' => $address->recipient_name ?? $user->name,
            'recipient_phone' => $address->recipient_phone ?? (string) $user->phone_number,
            'street' => $address->street ?? '',
            'city' => $address->city ?? '',
            'state' => $address->state ?? '',
            'postal_code' => $address->postal_code ?? '',
            'country' => $address->country ?? 'Indonesia',
        ];
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function roleLabel(): string
    {
        return User::ROLE_LABELS[$this->user->role] ?? $this->user->role;
    }

    /**
     * Ukuran badan dan alamat hanya relevan untuk pembeli.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function tabs(): array
    {
        $tabs = ['data' => 'Data Diri', 'keamanan' => 'Keamanan'];

        if ($this->user->role === 'user') {
            $tabs += ['ukuran' => 'Ukuran Badan', 'alamat' => 'Alamat'];
        }

        return $tabs + ['tampilan' => 'Tampilan'];
    }

    public function selectTab(string $tab): void
    {
        if (array_key_exists($tab, $this->tabs)) {
            $this->tab = $tab;
            $this->resetErrorBag();
        }
    }

    /**
     * Validasi foto langsung setelah dipilih supaya pratinjau tidak dibuat dari
     * file yang bukan gambar.
     */
    public function updatedPhoto(): void
    {
        $this->validateOnly('photo', ['photo' => $this->photoRules()]);
    }

    public function saveProfile(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user->id)],
            'phoneNumber' => ['nullable', 'string', 'max:32'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'photo' => $this->photoRules(),
        ]);

        $attributes = [
            'name' => $this->name,
            'email' => $this->email,
            'phone_number' => $this->phoneNumber !== '' ? $this->phoneNumber : null,
            'gender' => $this->gender !== '' ? $this->gender : null,
        ];

        if ($this->photo) {
            $previousPhoto = $this->user->profile_picture;
            $attributes['profile_picture'] = $this->photo->store('avatars', 'public');

            if ($previousPhoto) {
                Storage::disk('public')->delete($previousPhoto);
            }

            $this->photo = null;
        }

        $this->user->update($attributes);

        $this->dispatch('notify', message: 'Profil berhasil disimpan.');
    }

    public function removePhoto(): void
    {
        if ($this->user->profile_picture) {
            Storage::disk('public')->delete($this->user->profile_picture);
            $this->user->update(['profile_picture' => null]);
        }

        $this->photo = null;

        $this->dispatch('notify', message: 'Foto profil dihapus.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->user->update(['password' => Hash::make($this->password)]);

        $this->reset('currentPassword', 'password', 'password_confirmation');

        $this->dispatch('notify', message: 'Kata sandi berhasil diganti.');
    }

    /**
     * Tema langsung disimpan saat dipilih. `theme-changed` mengganti `<html data-theme>`
     * tanpa memuat ulang halaman (lihat resources/js/app.js).
     */
    public function updatedTheme(): void
    {
        $this->validate(['theme' => ['required', Rule::in(array_keys(self::THEMES))]]);

        $this->user->update(['theme' => $this->theme]);

        $this->dispatch('theme-changed', theme: $this->theme);
        $this->dispatch('notify', message: 'Tampilan berhasil disimpan.');
    }

    public function saveMeasurements(): void
    {
        abort_unless($this->user->role === 'user', 403);

        $this->validate(
            collect(self::MEASUREMENTS)
                ->mapWithKeys(fn (array $measurement, string $column): array => [
                    "measurements.{$column}" => ['nullable', 'numeric', "between:{$measurement[2]},{$measurement[3]}"],
                ])
                ->all()
        );

        $values = collect(Arr::only($this->measurements, array_keys(self::MEASUREMENTS)))
            ->map(fn (string $value): ?string => $value !== '' ? $value : null)
            ->all();

        $bodyProfile = $this->user->defaultBodyProfile
            ?? $this->user->bodyProfiles()->make(['profile_name' => 'Profil Saya', 'is_default' => true]);

        $bodyProfile->fill([...$values, 'gender' => $this->user->gender])->save();

        $this->dispatch('notify', message: 'Ukuran badan berhasil disimpan.');
    }

    public function saveAddress(): void
    {
        abort_unless($this->user->role === 'user', 403);

        $this->validate([
            'address.label' => ['required', 'string', 'max:50'],
            'address.recipient_name' => ['required', 'string', 'max:255'],
            'address.recipient_phone' => ['required', 'string', 'max:32'],
            'address.street' => ['required', 'string', 'max:500'],
            'address.city' => ['required', 'string', 'max:100'],
            'address.state' => ['required', 'string', 'max:100'],
            'address.postal_code' => ['required', 'string', 'max:10'],
            'address.country' => ['required', 'string', 'max:64'],
        ]);

        $address = $this->user->defaultAddress
            ?? $this->user->addresses()->make(['is_default' => true]);

        $address->fill(Arr::only($this->address, array_keys(self::ADDRESS_FIELDS)))->save();

        $this->dispatch('notify', message: 'Alamat berhasil disimpan.');
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'phoneNumber' => 'phone number',
            'currentPassword' => 'current password',
            ...collect(self::MEASUREMENTS)->mapWithKeys(fn (array $measurement, string $column): array => [
                "measurements.{$column}" => Str::lower($measurement[0]),
            ])->all(),
            ...collect(self::ADDRESS_FIELDS)->mapWithKeys(fn (string $label, string $column): array => [
                "address.{$column}" => Str::lower($label),
            ])->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function photoRules(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
    }

    /**
     * "170.00" → "170", "42.50" → "42.5", null → "".
     */
    protected function formatMeasurement(mixed $value): string
    {
        return $value === null ? '' : rtrim(rtrim((string) $value, '0'), '.');
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Profil Saya' => null]"
        title="Profil Saya"
        description="Kelola data diri dan keamanan akunmu."
        :links="array_flip($this->user->accountLinks())"
    />

    <div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
        {{-- Kartu ringkas + menu tab --}}
        <aside class="rounded-md border border-line bg-surface">
            <div class="flex items-center gap-3 border-b border-line px-[18px] py-4">
                <x-ui.avatar :user="$this->user" class="size-12 text-[15px]" />

                <div class="min-w-0">
                    <p class="truncate text-[13.5px] font-semibold">{{ $this->user->name }}</p>
                    <p class="mt-0.5 text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">
                        {{ $this->roleLabel }}
                    </p>
                </div>
            </div>

            <nav class="flex gap-1 overflow-x-auto p-2 lg:flex-col" role="tablist" aria-label="Bagian profil">
                @foreach ($this->tabs as $key => $label)
                    <button
                        type="button"
                        role="tab"
                        wire:key="tab-{{ $key }}"
                        wire:click="selectTab('{{ $key }}')"
                        aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                        @class([
                            'shrink-0 cursor-pointer rounded-[2px] px-3 py-2.5 text-left text-[13px] font-medium transition-colors duration-150',
                            'bg-ink text-cream' => $tab === $key,
                            'hover:bg-line/60' => $tab !== $key,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </aside>

        <section class="rounded-md border border-line bg-surface px-[clamp(18px,3vw,28px)] py-6" role="tabpanel">
            @if ($tab === 'data')
                <form wire:submit="saveProfile" class="flex flex-col gap-5">
                    <div>
                        <h2 class="font-display text-xl font-normal">Data Diri</h2>
                        <p class="mt-1 text-[12.5px] text-muted">Nama dan foto ini tampil di navbar dan pesananmu.</p>
                    </div>

                    {{-- Foto profil --}}
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($photo && ! $errors->has('photo'))
                            <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau foto profil" class="size-20 shrink-0 rounded-full object-cover">
                        @else
                            <x-ui.avatar :user="$this->user" class="size-20 text-2xl" />
                        @endif

                        <div class="flex flex-col gap-2">
                            <div class="flex flex-wrap gap-2">
                                <label class="inline-flex cursor-pointer items-center rounded-[2px] border border-line px-4 py-2 text-[12px] font-semibold uppercase tracking-[0.04em] transition-colors duration-150 hover:border-ink">
                                    <span wire:loading.remove wire:target="photo">Pilih Foto</span>
                                    <span wire:loading wire:target="photo">Mengunggah…</span>
                                    <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                                </label>

                                @if ($this->user->profile_picture)
                                    <button
                                        type="button"
                                        wire:click="removePhoto"
                                        wire:confirm="Hapus foto profil?"
                                        class="cursor-pointer rounded-[2px] px-4 py-2 text-[12px] font-semibold uppercase tracking-[0.04em] text-muted transition-colors duration-150 hover:text-sale"
                                    >
                                        Hapus
                                    </button>
                                @endif
                            </div>

                            <p class="text-[11.5px] text-muted">JPG, PNG, atau WebP. Maksimal 2 MB.</p>
                            @error('photo') <p class="text-[12px] text-sale">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="name" class="block text-[12px] font-semibold">Nama Lengkap</label>
                            <input id="name" type="text" wire:model="name" value="{{ $name }}" autocomplete="name" class="mt-1.5 h-11 w-full rounded-[2px] border border-line bg-bg px-3 text-[13px] outline-none transition-colors focus:border-ink">
                            @error('name') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-[12px] font-semibold">Email</label>
                            <input id="email" type="email" wire:model="email" value="{{ $email }}" autocomplete="email" class="mt-1.5 h-11 w-full rounded-[2px] border border-line bg-bg px-3 text-[13px] outline-none transition-colors focus:border-ink">
                            @error('email') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="phoneNumber" class="block text-[12px] font-semibold">No. Telepon</label>
                            <input id="phoneNumber" type="tel" wire:model="phoneNumber" value="{{ $phoneNumber }}" autocomplete="tel" placeholder="08xxxxxxxxxx" class="mt-1.5 h-11 w-full rounded-[2px] border border-line bg-bg px-3 text-[13px] outline-none transition-colors placeholder:text-muted focus:border-ink">
                            @error('phoneNumber') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="gender" class="block text-[12px] font-semibold">Jenis Kelamin</label>
                            <select id="gender" wire:model="gender" class="mt-1.5 h-11 w-full rounded-[2px] border border-line bg-bg px-3 text-[13px] outline-none transition-colors focus:border-ink">
                                <option value="" @selected($gender === '')>Tidak disebutkan</option>
                                <option value="male" @selected($gender === 'male')>Pria</option>
                                <option value="female" @selected($gender === 'female')>Wanita</option>
                            </select>
                            @error('gender') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <x-ui.save-button target="saveProfile">Simpan Perubahan</x-ui.save-button>
                </form>
            @elseif ($tab === 'keamanan')
                <form wire:submit="updatePassword" class="flex max-w-md flex-col gap-4">
                    <div>
                        <h2 class="font-display text-xl font-normal">Ganti Kata Sandi</h2>
                        <p class="mt-1 text-[12.5px] text-muted">Gunakan minimal 8 karakter.</p>
                    </div>

                    @foreach (['currentPassword' => ['Kata Sandi Saat Ini', 'current-password'], 'password' => ['Kata Sandi Baru', 'new-password'], 'password_confirmation' => ['Ulangi Kata Sandi Baru', 'new-password']] as $field => [$label, $autocomplete])
                        <div wire:key="password-field-{{ $field }}">
                            <label for="{{ $field }}" class="block text-[12px] font-semibold">{{ $label }}</label>
                            <input id="{{ $field }}" type="password" wire:model="{{ $field }}" autocomplete="{{ $autocomplete }}" class="mt-1.5 h-11 w-full rounded-[2px] border border-line bg-bg px-3 text-[13px] outline-none transition-colors focus:border-ink">
                            @error($field) <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                        </div>
                    @endforeach

                    <x-ui.save-button target="updatePassword">Ganti Kata Sandi</x-ui.save-button>
                </form>
            @elseif ($tab === 'ukuran')
                <form wire:submit="saveMeasurements" class="flex flex-col gap-5">
                    <div>
                        <h2 class="font-display text-xl font-normal">Ukuran Badan</h2>
                        <p class="mt-1 text-[12.5px] text-muted">Dipakai untuk merekomendasikan ukuran yang pas di setiap produk. Kosongkan yang tidak kamu ketahui.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($this::MEASUREMENTS as $column => [$label, $unit, $min, $max])
                            <div wire:key="measurement-{{ $column }}">
                                <label for="measurement-{{ $column }}" class="block text-[12px] font-semibold">{{ $label }}</label>
                                <div class="relative mt-1.5">
                                    <input
                                        id="measurement-{{ $column }}"
                                        type="number"
                                        inputmode="decimal"
                                        step="0.5"
                                        min="{{ $min }}"
                                        max="{{ $max }}"
                                        wire:model="measurements.{{ $column }}"
                                        value="{{ $measurements[$column] ?? '' }}"
                                        class="h-11 w-full rounded-[2px] border border-line bg-bg pl-3 pr-11 text-[13px] outline-none transition-colors focus:border-ink"
                                    >
                                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[12px] text-muted">{{ $unit }}</span>
                                </div>
                                @error("measurements.{$column}") <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>

                    <x-ui.save-button target="saveMeasurements">Simpan Ukuran</x-ui.save-button>
                </form>
            @elseif ($tab === 'alamat')
                <form wire:submit="saveAddress" class="flex flex-col gap-5">
                    <div>
                        <h2 class="font-display text-xl font-normal">Alamat Utama</h2>
                        <p class="mt-1 text-[12.5px] text-muted">Alamat ini otomatis dipakai saat checkout.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($this::ADDRESS_FIELDS as $column => $label)
                            <div wire:key="address-{{ $column }}" @class(['sm:col-span-2' => $column === 'street'])>
                                <label for="address-{{ $column }}" class="block text-[12px] font-semibold">{{ $label }}</label>

                                @if ($column === 'street')
                                    <textarea id="address-{{ $column }}" wire:model="address.{{ $column }}" rows="3" autocomplete="street-address" class="mt-1.5 w-full rounded-[2px] border border-line bg-bg px-3 py-2.5 text-[13px] outline-none transition-colors focus:border-ink">{{ $address[$column] ?? '' }}</textarea>
                                @else
                                    <input id="address-{{ $column }}" type="text" wire:model="address.{{ $column }}" value="{{ $address[$column] ?? '' }}" class="mt-1.5 h-11 w-full rounded-[2px] border border-line bg-bg px-3 text-[13px] outline-none transition-colors focus:border-ink">
                                @endif

                                @error("address.{$column}") <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>

                    <x-ui.save-button target="saveAddress">Simpan Alamat</x-ui.save-button>
                </form>
            @elseif ($tab === 'tampilan')
                <div class="flex flex-col gap-5">
                    <div>
                        <h2 class="font-display text-xl font-normal">Tampilan</h2>
                        <p class="mt-1 text-[12.5px] text-muted">Tema tersimpan di akunmu dan langsung dipakai di semua halaman.</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Tema">
                        @foreach ($this::THEMES as $value => $label)
                            <label
                                wire:key="theme-{{ $value }}"
                                class="flex cursor-pointer items-center gap-3 rounded-[2px] border border-line px-4 py-3.5 text-[13px] font-medium transition-colors duration-150 hover:border-ink has-checked:border-ink has-checked:bg-cream has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ink"
                            >
                                <input
                                    type="radio"
                                    name="theme"
                                    value="{{ $value }}"
                                    wire:model.live="theme"
                                    @checked($theme === $value)
                                    class="sr-only"
                                >

                                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="{{ $this::THEME_ICONS[$value] }}" />
                                </svg>

                                {{ $label }}
                            </label>
                        @endforeach
                    </div>

                    @error('theme') <p class="text-[12px] text-sale">{{ $message }}</p> @enderror

                    <p class="text-[11.5px] text-muted">"Ikuti Perangkat" memakai mode terang/gelap dari pengaturan HP atau laptopmu.</p>
                </div>
            @endif
        </section>
    </div>
</div>
