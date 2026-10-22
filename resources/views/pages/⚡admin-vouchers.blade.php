<?php

use App\Models\Voucher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin dan superadmin membuat kode promo yang ditukar pembeli di checkout:
 * persentase (bisa dibatasi maksimal rupiah, mis. 10% maks. Rp5.000) atau
 * potongan rupiah, dengan kuota total ("100 orang pertama") dan batas per pembeli.
 */
new #[Title('Kode Promo')] class extends Component
{
    use WithPagination;

    /**
     * Warna lencana status: nada => class utuh.
     *
     * @var array<string, string>
     */
    public const STATUS_TONES = [
        'success' => 'bg-success/12 text-success',
        'sale' => 'bg-sale/10 text-sale',
        'neutral' => 'bg-cream',
        'muted' => 'border border-line text-muted',
    ];

    #[Url(as: 'q')]
    public string $search = '';

    /** Voucher yang sedang diubah; null = form membuat kode baru. */
    #[Locked]
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $type = 'percentage';

    public string $value = '';

    public string $maxDiscount = '';

    public string $minSubtotal = '';

    public string $usageLimit = '';

    /** Bawaan 1: kuota 100 berarti 100 pembeli berbeda. */
    public string $perUserLimit = '1';

    public string $startsAt = '';

    public string $expiresAt = '';

    public bool $isActive = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Voucher>
     */
    #[Computed]
    public function vouchers(): LengthAwarePaginator
    {
        return Voucher::query()
            ->withCount([
                'orders',
                'orders as redemptions_count' => fn ($query) => $query->whereNotIn('status', Voucher::RELEASED_ORDER_STATUSES),
            ])
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('code', 'like', '%'.$this->search.'%')
                    ->orWhere('name', 'like', '%'.$this->search.'%');
            }))
            ->latest()
            ->paginate(15);
    }

    /**
     * Voucher sementara dari isian form untuk ringkasan di bawah form.
     */
    #[Computed]
    public function preview(): ?Voucher
    {
        if (! is_numeric($this->value) || (float) $this->value <= 0) {
            return null;
        }

        return new Voucher($this->voucherAttributes());
    }

    /**
     * @return array{0: string, 1: string} [label, nada di STATUS_TONES]
     */
    public function statusOf(Voucher $voucher): array
    {
        return match (true) {
            ! $voucher->is_active => ['Nonaktif', 'muted'],
            (bool) $voucher->expires_at?->isPast() => ['Kedaluwarsa', 'muted'],
            (bool) $voucher->starts_at?->isFuture() => ['Terjadwal', 'neutral'],
            $voucher->usage_limit !== null && $voucher->redemptions_count >= $voucher->usage_limit => ['Kuota habis', 'sale'],
            default => ['Aktif', 'success'],
        };
    }

    public function edit(int $voucherId): void
    {
        $voucher = Voucher::findOrFail($voucherId);

        $this->editingId = $voucher->id;
        $this->fill([
            'code' => $voucher->code,
            'name' => $voucher->name,
            'type' => $voucher->type,
            'value' => $this->plainNumber($voucher->value),
            'maxDiscount' => $this->plainNumber($voucher->max_discount),
            'minSubtotal' => (float) $voucher->min_subtotal > 0 ? $this->plainNumber($voucher->min_subtotal) : '',
            'usageLimit' => (string) $voucher->usage_limit,
            'perUserLimit' => (string) $voucher->per_user_limit,
            'startsAt' => (string) $voucher->starts_at?->format('Y-m-d\TH:i'),
            'expiresAt' => (string) $voucher->expires_at?->format('Y-m-d\TH:i'),
            'isActive' => $voucher->is_active,
        ]);
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->code = Voucher::normalizeCode($this->code);

        $this->validate();

        if ($this->editingId) {
            Voucher::findOrFail($this->editingId)->update($this->voucherAttributes());
            $message = "Kode {$this->code} diperbarui.";
        } else {
            Voucher::create($this->voucherAttributes());
            $message = "Kode {$this->code} dibuat dan siap ditukar pembeli.";
        }

        $this->resetForm();
        $this->dispatch('notify', message: $message);
    }

    public function toggle(int $voucherId): void
    {
        $voucher = Voucher::findOrFail($voucherId);
        $voucher->update(['is_active' => ! $voucher->is_active]);

        $this->dispatch('notify', message: "Kode {$voucher->code} ".($voucher->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    /**
     * Hanya kode yang belum pernah dipakai. Yang sudah dipakai cukup dinonaktifkan
     * supaya kuotanya tetap tercatat.
     */
    public function delete(int $voucherId): void
    {
        $voucher = Voucher::findOrFail($voucherId);

        abort_if($voucher->orders()->exists(), 409);

        $voucher->delete();

        if ($this->editingId === $voucherId) {
            $this->resetForm();
        }

        $this->dispatch('notify', message: "Kode {$voucher->code} dihapus.");
    }

    /**
     * Kolom `vouchers` dari isian form. Batas maksimal hanya berlaku untuk persentase.
     *
     * @return array<string, mixed>
     */
    private function voucherAttributes(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'value' => (float) $this->value,
            'max_discount' => $this->type === 'percentage' && $this->maxDiscount !== '' ? (float) $this->maxDiscount : null,
            'min_subtotal' => $this->minSubtotal === '' ? 0 : (float) $this->minSubtotal,
            'usage_limit' => $this->usageLimit === '' ? null : (int) $this->usageLimit,
            'per_user_limit' => $this->perUserLimit === '' ? null : (int) $this->perUserLimit,
            'starts_at' => $this->startsAt ?: null,
            'expires_at' => $this->expiresAt ?: null,
            'is_active' => $this->isActive,
        ];
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'code', 'name', 'type', 'value', 'maxDiscount', 'minSubtotal', 'usageLimit', 'perUserLimit', 'startsAt', 'expiresAt', 'isActive');
        $this->resetErrorBag();
    }

    /**
     * "5000.00" → "5000" supaya input angka tidak menampilkan desimal yang tidak perlu.
     */
    private function plainNumber(mixed $number): string
    {
        return $number === null ? '' : rtrim(rtrim(number_format((float) $number, 2, '.', ''), '0'), '.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('vouchers', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Voucher::TYPE_LABELS))],
            'value' => ['required', 'numeric', 'min:1', ...($this->type === 'percentage' ? ['max:100'] : [])],
            'maxDiscount' => ['nullable', 'numeric', 'min:1'],
            'minSubtotal' => ['nullable', 'numeric', 'min:0'],
            'usageLimit' => ['nullable', 'integer', 'min:1'],
            'perUserLimit' => ['nullable', 'integer', 'min:1'],
            'startsAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date', ...($this->startsAt !== '' ? ['after:startsAt'] : [])],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'code.regex' => 'Kode hanya boleh huruf, angka, dan tanda minus.',
            'code.unique' => 'Kode ini sudah dipakai.',
            'value.max' => 'Persentase maksimal 100.',
            'expiresAt.after' => 'Tanggal berakhir harus setelah tanggal mulai.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'code' => 'code',
            'name' => 'description',
            'value' => 'discount value',
            'maxDiscount' => 'maximum discount',
            'minSubtotal' => 'minimum purchase',
            'usageLimit' => 'quota',
            'perUserLimit' => 'limit per buyer',
            'startsAt' => 'start date',
            'expiresAt' => 'end date',
        ];
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Admin' => route('admin.dashboard'), 'Kode Promo' => null]"
        title="Kode Promo"
        description="Buat kode diskon yang ditukar pembeli saat checkout: persentase, potongan rupiah, atau persentase dengan batas maksimal."
        :links="auth()->user()->adminAreaLinks()"
    />

    <div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="min-w-0">
            <label for="search" class="sr-only">Cari kode promo</label>
            <input
                id="search"
                type="search"
                wire:model.live.debounce.400ms="search"
                value="{{ $search }}"
                placeholder="Cari kode atau keterangan…"
                class="w-full max-w-sm rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px]"
            >

            @if ($this->vouchers->isEmpty())
                <p class="mt-4 rounded-md border border-line bg-surface px-[18px] py-6 text-center text-[13px] text-muted">
                    {{ $search === '' ? 'Belum ada kode promo. Buat yang pertama lewat form di samping.' : 'Tidak ada kode yang cocok.' }}
                </p>
            @else
                <div wire:loading.delay.class="pointer-events-none opacity-50" class="mt-4 overflow-x-auto rounded-md border border-line bg-surface transition-opacity duration-200">
                    <table class="w-full min-w-[720px] text-left text-[12.5px]">
                        <thead class="text-[11px] uppercase tracking-[0.1em] text-muted">
                            <tr>
                                <th class="px-[18px] py-3 font-semibold">Kode</th>
                                <th class="px-[18px] py-3 font-semibold">Potongan</th>
                                <th class="px-[18px] py-3 font-semibold">Terpakai</th>
                                <th class="px-[18px] py-3 font-semibold">Periode</th>
                                <th class="px-[18px] py-3 font-semibold">Status</th>
                                <th class="px-[18px] py-3"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->vouchers as $voucher)
                                @php([$statusLabel, $statusTone] = $this->statusOf($voucher))
                                <tr wire:key="voucher-{{ $voucher->id }}" @class(['border-t border-line align-top', 'bg-cream/60' => $editingId === $voucher->id])>
                                    <td class="px-[18px] py-3">
                                        <p class="font-bold tracking-[0.06em]">{{ $voucher->code }}</p>
                                        <p class="mt-0.5 max-w-[220px] text-[11.5px] text-muted">{{ $voucher->name }}</p>
                                    </td>
                                    <td class="px-[18px] py-3">
                                        <p class="font-medium">{{ $voucher->benefitLabel() }}</p>
                                        @if ((float) $voucher->min_subtotal > 0)
                                            <p class="mt-0.5 text-[11.5px] text-muted">Min. belanja Rp{{ number_format((float) $voucher->min_subtotal, 0, ',', '.') }}</p>
                                        @endif
                                    </td>
                                    <td class="px-[18px] py-3">
                                        <p class="font-medium">{{ number_format($voucher->redemptions_count, 0, ',', '.') }} / {{ $voucher->usage_limit === null ? '∞' : number_format($voucher->usage_limit, 0, ',', '.') }}</p>
                                        <p class="mt-0.5 text-[11.5px] text-muted">{{ $voucher->per_user_limit === null ? 'Tanpa batas per pembeli' : $voucher->per_user_limit.'× per pembeli' }}</p>
                                    </td>
                                    <td class="px-[18px] py-3 text-[12px] text-muted">
                                        @if ($voucher->starts_at || $voucher->expires_at)
                                            {{ $voucher->starts_at?->format('d M Y') ?? 'Sekarang' }} &ndash; {{ $voucher->expires_at?->format('d M Y') ?? 'seterusnya' }}
                                        @else
                                            Tanpa batas waktu
                                        @endif
                                    </td>
                                    <td class="px-[18px] py-3">
                                        <span @class(['whitespace-nowrap rounded-[3px] px-2 py-0.5 text-[11px] font-semibold', $this::STATUS_TONES[$statusTone]])>{{ $statusLabel }}</span>
                                    </td>
                                    <td class="px-[18px] py-3">
                                        <div class="flex flex-wrap justify-end gap-x-3 gap-y-1 text-[12px] font-semibold">
                                            <button type="button" wire:click="edit({{ $voucher->id }})" class="cursor-pointer underline-offset-4 hover:underline">Ubah</button>
                                            <button type="button" wire:click="toggle({{ $voucher->id }})" class="cursor-pointer underline-offset-4 hover:underline">{{ $voucher->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                            @if ($voucher->orders_count === 0)
                                                <button type="button" wire:click="delete({{ $voucher->id }})" wire:confirm="Hapus kode {{ $voucher->code }}?" class="cursor-pointer text-sale underline-offset-4 hover:underline">Hapus</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-ui.pager :paginator="$this->vouchers" />
            @endif
        </div>

        {{-- Form buat / ubah --}}
        <form wire:submit="save" class="rounded-md border border-line bg-surface lg:sticky lg:top-[calc(var(--spacing-header)+16px)]" novalidate>
            <header class="border-b border-line px-[18px] py-4">
                <h2 class="text-[12px] font-semibold uppercase tracking-[0.1em]">{{ $editingId ? 'Ubah Kode '.$code : 'Buat Kode Promo' }}</h2>
            </header>

            <div class="flex flex-col gap-4 px-[18px] py-5">
                <x-ui.input name="code" label="Kode" wire:model="code" :value="$code" maxlength="32" autocomplete="off" class="uppercase" placeholder="HEMAT10" hint="Huruf, angka, dan tanda minus. Pembeli boleh mengetik huruf kecil." />
                <x-ui.input name="name" label="Keterangan" wire:model="name" :value="$name" placeholder="Diskon 10% hingga Rp5.000" hint="Tampil ke pembeli saat kode dipakai." />

                <fieldset>
                    <legend class="text-[12px] font-semibold">Jenis Potongan</legend>
                    <div class="mt-1.5 grid grid-cols-2 gap-2">
                        @foreach (Voucher::TYPE_LABELS as $typeKey => $typeLabel)
                            <label wire:key="type-{{ $typeKey }}" class="flex cursor-pointer items-center gap-2 rounded-[2px] border border-line px-3 py-2.5 text-[12.5px] font-medium transition-colors has-checked:border-ink has-checked:bg-cream">
                                <input type="radio" name="type" value="{{ $typeKey }}" wire:model.live="type" @checked($type === $typeKey) class="size-4 accent-ink">
                                {{ $typeLabel }}
                            </label>
                        @endforeach
                    </div>
                    @error('type') <p class="mt-1 text-[12px] text-sale">{{ $message }}</p> @enderror
                </fieldset>

                @if ($type === 'percentage')
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input name="value" label="Persentase (%)" type="number" inputmode="decimal" min="1" max="100" step="0.01" wire:model.blur="value" :value="$value" placeholder="10" />
                        <x-ui.input name="maxDiscount" label="Maks. potongan (Rp)" type="number" inputmode="numeric" min="1" wire:model.blur="maxDiscount" :value="$maxDiscount" placeholder="5000" />
                    </div>
                    <p class="-mt-2 text-[11.5px] text-muted">Kosongkan maksimal kalau potongannya tidak dibatasi.</p>
                @else
                    <x-ui.input name="value" label="Potongan (Rp)" type="number" inputmode="numeric" min="1" wire:model.blur="value" :value="$value" placeholder="25000" />
                @endif

                <x-ui.input name="minSubtotal" label="Minimal belanja (Rp)" type="number" inputmode="numeric" min="0" wire:model.blur="minSubtotal" :value="$minSubtotal" placeholder="0" hint="Dihitung dari subtotal barang, tanpa ongkir." />

                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input name="usageLimit" label="Kuota total" type="number" inputmode="numeric" min="1" wire:model.blur="usageLimit" :value="$usageLimit" placeholder="100" />
                    <x-ui.input name="perUserLimit" label="Per pembeli" type="number" inputmode="numeric" min="1" wire:model.blur="perUserLimit" :value="$perUserLimit" placeholder="1" />
                </div>
                <p class="-mt-2 text-[11.5px] text-muted">Kuota 100 dengan 1× per pembeli = hanya 100 orang pertama. Kosongkan untuk tanpa batas. Pesanan yang batal sebelum dibayar mengembalikan kuotanya.</p>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <x-ui.input name="startsAt" label="Mulai berlaku" type="datetime-local" wire:model="startsAt" :value="$startsAt" />
                    <x-ui.input name="expiresAt" label="Berakhir" type="datetime-local" wire:model="expiresAt" :value="$expiresAt" />
                </div>

                <label class="flex w-fit cursor-pointer items-center gap-2 text-[12.5px]">
                    <input type="checkbox" wire:model="isActive" @checked($isActive) class="size-4 accent-ink">
                    Aktif, bisa langsung ditukar
                </label>

                @if ($this->preview)
                    <p class="rounded-[2px] bg-cream px-3 py-2.5 text-[12px] leading-relaxed">
                        <span class="font-semibold">Ringkasan:</span>
                        {{ $this->preview->benefitLabel() }}
                        @if ((float) $this->preview->min_subtotal > 0)
                            &middot; min. belanja Rp{{ number_format((float) $this->preview->min_subtotal, 0, ',', '.') }}
                        @endif
                        &middot; {{ $this->preview->usage_limit ? 'kuota '.number_format($this->preview->usage_limit, 0, ',', '.') : 'tanpa kuota' }}
                        &middot; {{ $this->preview->per_user_limit ? $this->preview->per_user_limit.'× per pembeli' : 'tanpa batas per pembeli' }}.
                        Contoh: belanja Rp{{ number_format(max(200000, (float) $this->preview->min_subtotal), 0, ',', '.') }} hemat
                        <span class="font-semibold">Rp{{ number_format($this->preview->discountFor(max(200000, (float) $this->preview->min_subtotal)), 0, ',', '.') }}</span>.
                    </p>
                @endif
            </div>

            <footer class="flex gap-2 border-t border-line px-[18px] py-4">
                <x-ui.button target="save">{{ $editingId ? 'Simpan Perubahan' : 'Buat Kode' }}</x-ui.button>
                @if ($editingId)
                    <button type="button" wire:click="cancelEdit" class="inline-flex h-11 shrink-0 cursor-pointer items-center rounded-[2px] border border-line px-4 text-[11.5px] font-bold uppercase tracking-[0.1em] transition-colors hover:border-ink">Batal</button>
                @endif
            </footer>
        </form>
    </div>
</div>
