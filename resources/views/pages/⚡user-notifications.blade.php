<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Kabar status paket untuk pembeli (dikemas, dikirim, sampai, dibatalkan,
 * dana dikembalikan). Diisi `App\Notifications\PackageStatusChanged`.
 */
new #[Title('Notifikasi')] class extends Component
{
    use WithPagination;

    /**
     * @var array<string, string>
     */
    public const FILTERS = [
        '' => 'Semua',
        'unread' => 'Belum Dibaca',
    ];

    #[Url]
    public string $filter = '';

    public function mount(): void
    {
        if (! array_key_exists($this->filter, self::FILTERS)) {
            $this->filter = '';
        }
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        return Auth::user()->notifications()
            ->when($this->filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->paginate(15);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    /**
     * Menandai dibaca lalu membuka pesanan yang dimaksud.
     */
    public function open(string $id): void
    {
        $notification = Auth::user()->notifications()->find($id);

        abort_if($notification === null, 404);

        $notification->markAsRead();

        $this->redirectRoute('user.orders.show', $notification->data['order_number'], navigate: true);
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        unset($this->notifications, $this->unreadCount);

        $this->dispatch('notify', message: 'Semua notifikasi ditandai sudah dibaca.');
    }
};
?>

<div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
    @php
        /** @var array<string, string> Status paket => class warna titik (nama class utuh supaya terpindai Tailwind). */
        $dotColors = [
            'processing' => 'bg-ink',
            'shipped' => 'bg-ink',
            'delivered' => 'bg-success',
            'cancelled' => 'bg-sale',
            'refunded' => 'bg-sale',
        ];
    @endphp

    <x-ui.area-header
        :breadcrumbs="['Beranda' => route('home'), 'Akun' => route('profile'), 'Notifikasi' => null]"
        title="Notifikasi"
        description="Kabar terbaru status pesananmu: dikemas, dikirim, sampai, atau dibatalkan."
        :links="['profile' => 'Profil', 'user.orders' => 'Pesanan', 'user.notifications' => 'Notifikasi', 'user.wishlist' => 'Wishlist', 'user.cart' => 'Keranjang']"
    />

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2" role="tablist" aria-label="Filter notifikasi">
            @foreach ($this::FILTERS as $value => $label)
                <button
                    type="button"
                    role="tab"
                    wire:click="$set('filter', '{{ $value }}')"
                    aria-selected="{{ $filter === $value ? 'true' : 'false' }}"
                    @class([
                        'cursor-pointer rounded-[2px] border px-4 py-2 text-[12px] font-semibold transition-colors',
                        'border-ink bg-ink text-cream' => $filter === $value,
                        'border-line hover:border-ink' => $filter !== $value,
                    ])
                >
                    {{ $label }}
                    @if ($value === 'unread' && $this->unreadCount > 0)
                        ({{ $this->unreadCount }})
                    @endif
                </button>
            @endforeach
        </div>

        @if ($this->unreadCount > 0)
            <button type="button" wire:click="markAllAsRead" class="cursor-pointer text-[12px] font-semibold text-muted transition-colors hover:text-ink">
                Tandai semua sudah dibaca
            </button>
        @endif
    </div>

    @if ($this->notifications->isEmpty())
        <div class="mt-4 rounded-md border border-line bg-surface px-[18px] py-12 text-center">
            <p class="font-display text-xl">{{ $filter === 'unread' ? 'Semua sudah dibaca' : 'Belum ada notifikasi' }}</p>
            <p class="mt-1.5 text-[13px] text-muted">Kabar pesanan muncul di sini begitu penjual mengemas, mengirim, atau membatalkan paketmu.</p>
        </div>
    @else
        <ul wire:loading.delay.class="pointer-events-none opacity-50" class="mt-4 divide-y divide-line overflow-hidden rounded-md border border-line bg-surface transition-opacity">
            @foreach ($this->notifications as $notification)
                @php
                    $data = $notification->data;
                    $isUnread = $notification->read_at === null;
                @endphp

                <li wire:key="notification-{{ $notification->id }}">
                    <button
                        type="button"
                        wire:click="open('{{ $notification->id }}')"
                        @class([
                            'flex w-full cursor-pointer items-start gap-3 px-[18px] py-4 text-left transition-colors hover:bg-bg',
                            'bg-cream/40' => $isUnread,
                        ])
                    >
                        <span @class([
                            'mt-1.5 size-2.5 shrink-0 rounded-full',
                            $isUnread ? ($dotColors[$data['status'] ?? ''] ?? 'bg-ink') : 'bg-line',
                        ]) aria-hidden="true"></span>

                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5">
                                <span @class(['text-[13.5px]', 'font-semibold' => $isUnread, 'font-medium' => ! $isUnread])>
                                    {{ $data['title'] ?? 'Status pesanan berubah' }}
                                    @if ($isUnread)
                                        <span class="sr-only">(belum dibaca)</span>
                                    @endif
                                </span>
                                <span class="text-[11.5px] text-muted" title="{{ $notification->created_at->format('d M Y, H:i') }}">{{ $notification->created_at->locale('id')->diffForHumans() }}</span>
                            </span>
                            <span class="mt-1 block text-[12.5px] leading-relaxed text-muted">{{ $data['message'] ?? '' }}</span>
                            <span class="mt-1 block text-[11.5px] text-muted">Pesanan {{ $data['order_number'] ?? '' }} &middot; {{ $data['store_name'] ?? '' }}</span>
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>

        <x-ui.pager :paginator="$this->notifications" />
    @endif
</div>
