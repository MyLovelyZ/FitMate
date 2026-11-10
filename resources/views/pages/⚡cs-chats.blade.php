<?php

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Live chat untuk CS: antrean percakapan di kiri (terbuka dulu, pesan terbaru
 * di atas, dengan jumlah belum dibaca), isi percakapan terpilih di kanan.
 * Data dimuat ulang tiap beberapa detik lewat wire:poll.
 */
new #[Title('Live Chat')] class extends Component
{
    #[Url(as: 'c')]
    public ?int $conversationId = null;

    public string $body = '';

    public function mount(): void
    {
        // Dibuka dari tiket bantuan: `?user=` langsung membuka/memulai chat dengan pembeli itu.
        $customer = User::find((int) request()->query('user'));

        if ($customer?->canShop()) {
            $this->conversationId = ChatConversation::forCustomer($customer)->id;
        }
    }

    /**
     * @return Collection<int, ChatConversation>
     */
    #[Computed]
    public function conversations(): Collection
    {
        $staffId = Auth::id();

        return ChatConversation::with(['customer', 'assignee'])
            ->withCount(['messages as unread_count' => fn ($query) => $query->where('sender_id', '!=', $staffId)->whereNull('read_at')])
            ->orderByRaw("status = 'open' desc")
            ->orderByDesc('last_message_at')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function selected(): ?ChatConversation
    {
        return $this->conversationId ? ChatConversation::with('customer')->find($this->conversationId) : null;
    }

    public function select(int $conversationId): void
    {
        $this->conversationId = $conversationId;
        $this->resetErrorBag();
        unset($this->selected, $this->conversations);
    }

    public function send(): void
    {
        $conversation = $this->selected;

        abort_if($conversation === null, 404);

        $this->validate(
            ['body' => ['required', 'string', 'max:'.ChatMessage::MAX_LENGTH]],
            ['body.required' => 'Tulis balasan dulu.', 'body.max' => 'Pesan maksimal '.ChatMessage::MAX_LENGTH.' karakter.'],
        );

        // Membalas percakapan yang sudah ditutup = membukanya lagi untuk pelanggan itu.
        if (! $conversation->isOpen()) {
            $conversation = ChatConversation::forCustomer($conversation->customer);
            $this->conversationId = $conversation->id;
        }

        $conversation->send(Auth::user(), $this->body);

        $this->reset('body');
        unset($this->selected, $this->conversations);
    }

    public function close(): void
    {
        $this->selected?->close();
        unset($this->selected, $this->conversations);

        $this->dispatch('notify', message: 'Percakapan ditutup.');
    }

    public function refreshMessages(): void
    {
        unset($this->selected, $this->conversations);
    }

    public function render(): View
    {
        $this->selected?->markReadBy(Auth::user());

        return $this->view();
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Customer Service' => route('cs.dashboard'), 'Live Chat' => null]"
        title="Live Chat"
        description="Percakapan langsung dengan pelanggan."
        :links="['cs.dashboard' => 'Dashboard', 'cs.tickets.index' => 'Tiket', 'cs.chats.index' => 'Live Chat']"
    />

    <div wire:poll.5s.visible="refreshMessages" class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
        {{-- Antrean --}}
        <section class="rounded-md border border-line bg-surface">
            <h2 class="border-b border-line px-[18px] py-3 text-[12px] font-semibold uppercase tracking-[0.1em]">Percakapan</h2>

            @if ($this->conversations->isEmpty())
                <p class="px-[18px] py-8 text-center text-[13px] text-muted">Belum ada percakapan.</p>
            @else
                <ul class="max-h-[560px] divide-y divide-line overflow-y-auto">
                    @foreach ($this->conversations as $conversation)
                        <li wire:key="conversation-{{ $conversation->id }}">
                            <button
                                type="button"
                                wire:click="select({{ $conversation->id }})"
                                @class([
                                    'flex w-full cursor-pointer items-start justify-between gap-3 px-[18px] py-3 text-left transition-colors hover:bg-bg',
                                    'bg-cream' => $conversation->id === $conversationId,
                                ])
                            >
                                <span class="min-w-0">
                                    <span class="block truncate text-[13px] font-semibold">{{ $conversation->customer?->name }}</span>
                                    <span class="block text-[11.5px] text-muted">
                                        {{ $conversation->isOpen() ? 'Terbuka' : 'Ditutup' }}
                                        @if ($conversation->assignee) &middot; {{ $conversation->assignee->name }} @endif
                                        @if ($conversation->last_message_at) &middot; {{ $conversation->last_message_at->diffForHumans() }} @endif
                                    </span>
                                </span>
                                @if ($conversation->unread_count > 0)
                                    <span class="shrink-0 rounded-full bg-sale px-2 py-0.5 text-[11px] font-bold text-cream" aria-label="{{ $conversation->unread_count }} pesan belum dibaca">{{ $conversation->unread_count }}</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Isi percakapan --}}
        <section class="flex h-[min(70vh,600px)] flex-col rounded-md border border-line bg-surface">
            @if (! $this->selected)
                <p class="m-auto px-6 text-center text-[13px] text-muted">Pilih percakapan di sebelah kiri.</p>
            @else
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-[18px] py-3">
                    <div>
                        <p class="text-[13.5px] font-semibold">{{ $this->selected->customer?->name }}</p>
                        <p class="text-[11.5px] text-muted">{{ $this->selected->customer?->email }}</p>
                    </div>
                    @if ($this->selected->isOpen())
                        <button type="button" wire:click="close" wire:confirm="Tutup percakapan ini?" class="cursor-pointer rounded-[2px] border border-line px-3 py-1.5 text-[12px] font-semibold transition-colors hover:border-ink">Tutup Percakapan</button>
                    @else
                        <span class="rounded-[3px] bg-line px-2 py-0.5 text-[11px] font-semibold text-muted">Ditutup</span>
                    @endif
                </div>

                <ol class="flex flex-1 flex-col gap-3 overflow-y-auto px-[18px] py-4" x-data x-init="$el.scrollTop = $el.scrollHeight" x-on:livewire:commit.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                    @forelse ($this->selected->messages()->with('sender')->get() as $message)
                        @php($fromCustomer = $message->sender_id === $this->selected->user_id)
                        <li wire:key="msg-{{ $message->id }}" @class(['flex max-w-[80%] flex-col', 'self-start items-start' => $fromCustomer, 'self-end items-end' => ! $fromCustomer])>
                            <span class="text-[11px] text-muted">{{ $message->sender?->name }} &middot; {{ $message->created_at->format('d M, H:i') }}</span>
                            <p @class(['mt-1 whitespace-pre-line rounded-md px-3.5 py-2.5 text-[13px] leading-[1.6]', 'border border-line bg-bg' => $fromCustomer, 'bg-ink text-cream' => ! $fromCustomer])>{{ $message->body }}</p>
                        </li>
                    @empty
                        <li class="m-auto text-center text-[13px] text-muted">Belum ada pesan. Sapa pelanggan ini lebih dulu.</li>
                    @endforelse
                </ol>

                <form wire:submit="send" class="flex items-end gap-2 border-t border-line p-3">
                    <label for="reply" class="sr-only">Balasan</label>
                    <textarea
                        id="reply"
                        rows="2"
                        wire:model="body"
                        maxlength="{{ App\Models\ChatMessage::MAX_LENGTH }}"
                        placeholder="{{ $this->selected->isOpen() ? 'Tulis balasan…' : 'Membalas akan membuka percakapan baru…' }}"
                        x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"
                        class="min-w-0 flex-1 resize-none rounded-[2px] border border-line bg-bg px-3 py-2 text-[13px] outline-none placeholder:text-muted focus:border-ink"
                    >{{ $body }}</textarea>
                    <x-ui.button target="send" class="w-auto! shrink-0 px-6">Kirim</x-ui.button>
                </form>
                @error('body') <p class="px-3 pb-3 text-[12px] text-sale">{{ $message }}</p> @enderror
            @endif
        </section>
    </div>
</div>
