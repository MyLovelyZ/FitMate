{{--
    Notifikasi singkat di bawah layar. Komponen Livewire memicunya lewat
    `$this->dispatch('notify', message: 'Profil tersimpan.')`, opsional dengan
    tautan: `url: route('user.cart'), action: 'Lihat Keranjang'`.
--}}
<div
    x-data="{ toasts: [], nextId: 0 }"
    x-on:notify.window="
        const id = nextId++;
        toasts.push({ id, message: $event.detail.message, url: $event.detail.url ?? null, action: $event.detail.action ?? null });
        setTimeout(() => toasts = toasts.filter((toast) => toast.id !== id), toasts.at(-1).url ? 5000 : 3500);
    "
    class="pointer-events-none fixed bottom-5 left-1/2 z-[999] flex w-[min(92vw,380px)] -translate-x-1/2 flex-col gap-2"
    role="status"
    aria-live="polite"
>
    <template x-for="toast in toasts" x-bind:key="toast.id">
        <div
            class="pointer-events-auto flex items-center gap-2.5 rounded-md bg-ink px-4 py-3 text-[13px] text-cream shadow-[0_8px_24px_rgba(10,25,47,0.18)]"
        >
            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m5 12.5 4.5 4.5L19 7.5" />
            </svg>
            <span class="min-w-0 flex-1" x-text="toast.message"></span>

            <template x-if="toast.url">
                <a
                    x-bind:href="toast.url"
                    x-on:click.prevent="Livewire.navigate(toast.url)"
                    class="shrink-0 font-semibold underline underline-offset-4"
                    x-text="toast.action ?? 'Lihat'"
                ></a>
            </template>
        </div>
    </template>
</div>
