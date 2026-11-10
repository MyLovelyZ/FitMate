<?php

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Live chat pelanggan dengan CS FitMate. Percakapan baru dibuat saat pesan
 * pertama dikirim; halaman memuat ulang pesan tiap beberapa detik (wire:poll).
 */
new #[Title('Chat dengan CS')] class extends Component
{
    public string $body = '';

    /**
     * Percakapan terbuka, atau yang terakhir ditutup supaya riwayatnya terlihat.
     */
    #[Computed]
    public function conversation(): ?ChatConversation
    {
        return ChatConversation::where('user_id', Auth::id())
            ->orderByRaw("status = 'open' desc")
            ->latest('id')
            ->first();
    }

    public function send(): void
    {
        $this->validate(
            ['body' => ['required', 'string', 'max:'.ChatMessage::MAX_LENGTH]],
            ['body.required' => 'Tulis pesanmu dulu.', 'body.max' => 'Pesan maksimal '.ChatMessage::MAX_LENGTH.' karakter.'],
        );

        ChatConversation::forCustomer(Auth::user())->send(Auth::user(), $this->body);

        $this->reset('body');
        unset($this->conversation);
    }

    /**
     * Dipanggil wire:poll: pesan CS yang tampil dianggap sudah dibaca.
     */
    public function refreshMessages(): void
    {
        unset($this->conversation);
    }

    public function render(): View
    {
        $this->conversation?->markReadBy(Auth::user());

        return $this->view();
    }
};
?>

<div class="mx-auto max-w-[860px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Bantuan' => route('help'), 'Chat CS' => null]"
        title="Chat dengan CS"
        description="Tanya soal ukuran, pesanan, atau pengiriman. CS FitMate membalas di jam kerja (09.00–21.00 WIB)."
    />

    <section wire:poll.5s.visible="refreshMessages" class="mt-6 flex h-[min(70vh,560px)] flex-col rounded-md border border-line bg-surface">
        <ol class="flex flex-1 flex-col gap-3 overflow-y-auto px-[18px] py-4" x-data x-init="$el.scrollTop = $el.scrollHeight" x-on:livewire:commit.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
            @forelse ($this->conversation?->messages()->with('sender')->get() ?? [] as $message)
                @php($mine = $message->sender_id === auth()->id())
                <li wire:key="msg-{{ $message->id }}" @class(['flex flex-col max-w-[80%]', 'self-end items-end' => $mine, 'self-start items-start' => ! $mine])>
                    <span class="text-[11px] text-muted">{{ $mine ? 'Kamu' : ($message->sender?->name.' · CS FitMate') }} &middot; {{ $message->created_at->format('d M, H:i') }}</span>
                    <p @class(['mt-1 whitespace-pre-line rounded-md px-3.5 py-2.5 text-[13px] leading-[1.6]', 'bg-ink text-cream' => $mine, 'border border-line bg-bg' => ! $mine])>{{ $message->body }}</p>
                </li>
            @empty
                <li class="m-auto text-center text-[13px] text-muted">Belum ada pesan. Sapa CS kami untuk mulai.</li>
            @endforelse

            @if ($this->conversation && ! $this->conversation->isOpen())
                <li class="self-center rounded-[3px] bg-cream px-3 py-1 text-[11.5px] text-muted">Percakapan ditutup CS. Kirim pesan baru untuk membuka percakapan lagi.</li>
            @endif
        </ol>

        <form wire:submit="send" class="flex items-end gap-2 border-t border-line p-3">
            <label for="body" class="sr-only">Pesan</label>
            <textarea
                id="body"
                rows="2"
                wire:model="body"
                maxlength="{{ App\Models\ChatMessage::MAX_LENGTH }}"
                placeholder="Tulis pesan…"
                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"
                class="min-w-0 flex-1 resize-none rounded-[2px] border border-line bg-bg px-3 py-2 text-[13px] outline-none placeholder:text-muted focus:border-ink"
            >{{ $body }}</textarea>
            <x-ui.button target="send" class="w-auto! shrink-0 px-6">Kirim</x-ui.button>
        </form>
        @error('body') <p class="px-3 pb-3 text-[12px] text-sale">{{ $message }}</p> @enderror
    </section>
</div>
