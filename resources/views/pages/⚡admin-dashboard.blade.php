<?php

use App\Models\Dispute;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard Admin')] class extends Component
{
    /**
     * Angka ringkas untuk kartu statistik: label => nilai siap tampil.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'Pengguna' => number_format(User::count(), 0, ',', '.'),
            'Toko Aktif' => number_format(Store::where('status', 'active')->count(), 0, ',', '.'),
            'Pesanan' => number_format(Order::count(), 0, ',', '.'),
            'Sengketa Terbuka' => number_format(Dispute::whereIn('status', ['open', 'under_review'])->count(), 0, ',', '.'),
        ];
    }

    /**
     * Toko yang masih menunggu verifikasi, terbaru dulu.
     *
     * @return Collection<int, Store>
     */
    #[Computed]
    public function pendingStores(): Collection
    {
        return Store::with('owner')
            ->where('status', 'pending')
            ->orderBy('submitted_at')
            ->limit(10)
            ->get();
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Admin' => null]"
        title="Dashboard Admin"
        description="Ringkasan marketplace FitMate."
        :links="['admin.dashboard' => 'Dashboard', 'admin.stores.index' => 'Toko']"
    />

    {{-- Hitungan agregat dimuat lazy supaya kerangka halaman tampil lebih dulu. --}}
    @island(name: 'stats', lazy: true)
        @placeholder
            <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach (range(1, 4) as $index)
                    <x-ui.skeleton class="h-[86px]" />
                @endforeach
            </section>
        @endplaceholder

        <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($this->stats as $label => $value)
                <x-ui.stat :label="$label" :value="$value" />
            @endforeach
        </section>
    @endisland

    <section class="mt-10">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-[13px] font-semibold uppercase tracking-[0.1em]">Toko Menunggu Verifikasi</h2>
            <a href="{{ route('admin.stores.index') }}" wire:navigate.hover class="text-[12.5px] font-medium hover:underline">Lihat semua</a>
        </div>

        @island(name: 'pending-stores', lazy: true)
            @placeholder
                <x-ui.skeleton class="mt-3 h-40" />
            @endplaceholder

            @if ($this->pendingStores->isEmpty())
            <p class="mt-3 rounded-md border border-line bg-surface px-[18px] py-6 text-center text-[13px] text-muted">Tidak ada toko yang menunggu verifikasi.</p>
        @else
            <div class="mt-3 overflow-x-auto rounded-md border border-line bg-surface">
                <table class="w-full text-left text-[12.5px]">
                    <thead class="text-[11px] uppercase tracking-[0.1em] text-muted">
                        <tr>
                            <th class="px-[18px] py-3 font-semibold">Toko</th>
                            <th class="px-[18px] py-3 font-semibold">Pemilik</th>
                            <th class="px-[18px] py-3 font-semibold">Kota</th>
                            <th class="px-[18px] py-3 font-semibold">Diajukan</th>
                            <th class="px-[18px] py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->pendingStores as $store)
                            <tr wire:key="store-{{ $store->id }}" class="border-t border-line">
                                <td class="px-[18px] py-3 font-medium">{{ $store->name }}</td>
                                <td class="px-[18px] py-3">{{ $store->owner?->name }}</td>
                                <td class="px-[18px] py-3 text-muted">{{ $store->city ?? '—' }}</td>
                                <td class="px-[18px] py-3 text-muted">{{ ($store->submitted_at ?? $store->created_at)->format('d M Y') }}</td>
                                <td class="px-[18px] py-3 text-right">
                                    <a href="{{ route('admin.stores.show', $store) }}" wire:navigate.hover class="inline-flex items-center rounded-[2px] border border-line px-3 py-1.5 text-[12px] font-semibold transition-colors hover:border-ink">Tinjau</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        @endisland
    </section>
</div>
