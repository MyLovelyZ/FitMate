<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pengaturan aplikasi masih dibaca dari `config/` dan `.env`, belum ada tabel
 * settings. Halaman ini menampilkannya apa adanya (read-only).
 */
new #[Title('Pengaturan')] class extends Component
{
    /**
     * @return array<string, string>
     */
    #[Computed]
    public function settings(): array
    {
        return [
            'Nama Aplikasi' => (string) config('app.name'),
            'URL' => (string) config('app.url'),
            'Environment' => (string) config('app.env'),
            'Mode Debug' => config('app.debug') ? 'Aktif' : 'Nonaktif',
            'Zona Waktu' => (string) config('app.timezone'),
            'Bahasa' => (string) config('app.locale'),
            'Database' => (string) config('database.default'),
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
        ];
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Super Admin' => route('superadmin.dashboard'), 'Pengaturan' => null]"
        title="Pengaturan"
        description="Konfigurasi aplikasi saat ini. Ubah lewat file .env."
        :links="['superadmin.users.index' => 'Pengguna', 'superadmin.settings' => 'Pengaturan']"
    />

    <section class="mt-6 max-w-[800px] rounded-md border border-line bg-surface">
        @foreach ($this->settings as $label => $value)
            <div class="flex items-center justify-between gap-5 border-b border-line px-[18px] py-3.5 text-[12.5px] last:border-b-0">
                <span class="text-muted">{{ $label }}</span>
                <span class="font-semibold">{{ $value }}</span>
            </div>
        @endforeach
    </section>
</div>
