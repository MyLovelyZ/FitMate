<?php

use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Live chat belum punya tabel/model. Halaman ini sengaja hanya kerangka
 * supaya menu CS lengkap; isi percakapan menyusul.
 */
new #[Title('Live Chat')] class extends Component
{
    //
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Customer Service' => route('cs.dashboard'), 'Live Chat' => null]"
        title="Live Chat"
        description="Percakapan langsung dengan pelanggan."
        :links="['cs.dashboard' => 'Dashboard', 'cs.tickets.index' => 'Tiket', 'cs.chats.index' => 'Live Chat']"
    />

    <div class="mt-6 rounded-md border border-line bg-surface px-[18px] py-12 text-center">
        <p class="font-display text-xl">Belum ada percakapan</p>
        <p class="mt-1.5 text-[13px] text-muted">Fitur live chat sedang disiapkan. Sementara itu, tangani keluhan lewat menu Tiket.</p>
    </div>
</div>
