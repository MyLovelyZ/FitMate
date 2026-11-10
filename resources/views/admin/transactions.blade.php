{{--
    Back-office transaksi (AdminController@transactions). Statistik dari count()/sum(),
    tabel dari latest(): pesanan terbaru di baris paling atas. Tombol status dikirim
    ke AdminController@updateStatus (POST /admin/transactions/{id}/status).
--}}
@use('App\Models\Order')

<x-layouts::admin title="Transaksi">
    <div class="mx-auto max-w-[1280px] px-side pb-[clamp(48px,8vw,80px)] pt-[clamp(32px,6vw,56px)]">
        <x-ui.area-header
            :breadcrumbs="['Beranda' => route('home'), 'Admin' => route('admin.dashboard'), 'Transaksi' => null]"
            title="Transaksi"
            description="Semua pesanan masuk dari seluruh toko. Ubah status di sini; nota pembeli ikut berubah."
            :links="auth()->user()->adminAreaLinks()"
        />

        @if (session('success'))
            <p class="mt-6 flex items-center gap-2 rounded-md border border-success/30 bg-success/12 px-[18px] py-3.5 text-[13px] font-medium text-success" role="status">
                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                {{ session('success') }}
            </p>
        @endif

        @if (session('failure') || $errors->any())
            <div class="mt-6 rounded-md border border-sale/30 bg-sale/5 px-[18px] py-3.5 text-[13px] text-sale" role="alert">
                {{ session('failure') }}
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
            @foreach ($stats as $label => $value)
                <x-ui.stat :label="$label" :value="$value" />
            @endforeach
        </section>
        <p class="mt-2 text-[11.5px] text-muted">Omzet = jumlah total bayar pesanan berstatus Lunas, Diproses, Dikirim, dan Selesai. Komisi = komisi paket yang sudah selesai.</p>

        <form method="GET" action="{{ route('admin.transactions.index') }}" class="mt-8 flex flex-wrap items-end gap-2">
            <div class="w-full max-w-xs">
                <label for="q" class="sr-only">Cari pesanan</label>
                <input id="q" type="search" name="q" value="{{ $search }}" placeholder="No. pesanan, nama, atau email pembeli…" class="w-full rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px]">
            </div>
            <div>
                <label for="status" class="sr-only">Filter status</label>
                <select id="status" name="status" class="rounded-[2px] border border-line bg-surface px-3 py-2 text-[12.5px]">
                    <option value="" @selected($status === '')>Semua status</option>
                    @foreach (Order::STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="cursor-pointer rounded-[2px] bg-ink px-4 py-2 text-[11.5px] font-bold uppercase tracking-[0.1em] text-cream transition-opacity hover:opacity-85">Terapkan</button>
            @if ($search !== '' || $status !== '')
                <a href="{{ route('admin.transactions.index') }}" class="px-2 py-2 text-[12px] font-semibold text-muted hover:text-ink">Reset</a>
            @endif
        </form>

        @if ($orders->isEmpty())
            <p class="mt-4 rounded-md border border-line bg-surface px-[18px] py-6 text-center text-[13px] text-muted">Belum ada pesanan.</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-md border border-line bg-surface">
                <table class="w-full min-w-[960px] text-left text-[12.5px]">
                    <thead class="text-[11px] uppercase tracking-[0.1em] text-muted">
                        <tr>
                            <th class="px-[18px] py-3 font-semibold">No. Pesanan</th>
                            <th class="px-[18px] py-3 font-semibold">Pembeli</th>
                            <th class="px-[18px] py-3 font-semibold">Toko</th>
                            <th class="px-[18px] py-3 font-semibold">Total</th>
                            <th class="px-[18px] py-3 font-semibold">Status</th>
                            <th class="px-[18px] py-3 font-semibold">Masuk</th>
                            <th class="px-[18px] py-3 font-semibold">Ubah Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            @php($actions = $order->adminActions())
                            <tr class="border-t border-line align-top" data-order="{{ $order->order_number }}">
                                <td class="px-[18px] py-3 font-medium">{{ $order->order_number }}</td>
                                <td class="px-[18px] py-3">
                                    {{ $order->user?->name }}
                                    <span class="block text-[11.5px] text-muted">{{ $order->user?->email }}</span>
                                </td>
                                <td class="px-[18px] py-3">{{ $order->storeOrders->map(fn ($storeOrder) => $storeOrder->store?->displayName())->filter()->join(', ') }}</td>
                                <td class="px-[18px] py-3 whitespace-nowrap">Rp {{ number_format((float) $order->grand_total, 0, ',', '.') }}</td>
                                <td class="px-[18px] py-3">
                                    <x-ui.order-status :order="$order" />
                                    <span class="mt-1 block text-[11px] text-muted">{{ Order::STATUS_LABELS[$order->status] ?? $order->status }}</span>
                                </td>
                                <td class="px-[18px] py-3 whitespace-nowrap text-muted">{{ ($order->placed_at ?? $order->created_at)->format('d M Y, H:i') }}</td>
                                <td class="px-[18px] py-3">
                                    @if ($actions === [])
                                        <span class="text-[12px] text-muted">—</span>
                                    @else
                                        <div class="flex flex-col items-start gap-1.5">
                                            @foreach ($actions as $target => $label)
                                                @if ($target === 'shipped' || $target === 'cancelled')
                                                    {{-- Butuh isian tambahan (resi / alasan), jadi dibuka dulu. --}}
                                                    <details class="group">
                                                        <summary @class([
                                                            'cursor-pointer list-none rounded-[2px] px-3 py-1.5 text-[12px] font-semibold',
                                                            'bg-ink text-cream' => $target === 'shipped',
                                                            'text-muted hover:text-sale' => $target === 'cancelled',
                                                        ])>{{ $label }}</summary>
                                                        <form method="POST" action="{{ route('admin.transactions.status', $order) }}" class="mt-1.5 flex gap-1.5">
                                                            @csrf
                                                            <input type="hidden" name="status" value="{{ $target }}">
                                                            @if ($target === 'shipped')
                                                                <input type="text" name="tracking_number" maxlength="64" placeholder="No. resi" aria-label="Nomor resi {{ $order->order_number }}" class="h-9 w-36 rounded-[2px] border border-line bg-bg px-2 text-[12px]">
                                                            @else
                                                                <input type="text" name="reason" maxlength="255" placeholder="Alasan" aria-label="Alasan batal {{ $order->order_number }}" class="h-9 w-36 rounded-[2px] border border-line bg-bg px-2 text-[12px]">
                                                            @endif
                                                            <button type="submit" class="cursor-pointer rounded-[2px] border border-line px-3 text-[12px] font-semibold hover:border-ink" @if ($target === 'cancelled') onclick="return confirm('Batalkan pesanan ini? Stok dikembalikan dan dana yang sudah dibayar dikembalikan ke saldo pembeli.')" @endif>Simpan</button>
                                                        </form>
                                                    </details>
                                                @else
                                                    <form method="POST" action="{{ route('admin.transactions.status', $order) }}">
                                                        @csrf
                                                        <input type="hidden" name="status" value="{{ $target }}">
                                                        <button type="submit" class="cursor-pointer rounded-[2px] bg-ink px-3 py-1.5 text-[12px] font-semibold text-cream transition-opacity hover:opacity-85">{{ $label }}</button>
                                                    </form>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <nav class="mt-6 flex items-center justify-between gap-4 text-[12.5px]" aria-label="Halaman">
                    @if ($orders->onFirstPage())
                        <span class="rounded-[2px] border border-line px-4 py-2 font-semibold text-muted">Sebelumnya</span>
                    @else
                        <a href="{{ $orders->previousPageUrl() }}" class="rounded-[2px] border border-line px-4 py-2 font-semibold hover:border-ink">Sebelumnya</a>
                    @endif

                    <span class="text-muted">Halaman {{ $orders->currentPage() }} dari {{ $orders->lastPage() }}</span>

                    @if ($orders->hasMorePages())
                        <a href="{{ $orders->nextPageUrl() }}" class="rounded-[2px] border border-line px-4 py-2 font-semibold hover:border-ink">Berikutnya</a>
                    @else
                        <span class="rounded-[2px] border border-line px-4 py-2 font-semibold text-muted">Berikutnya</span>
                    @endif
                </nav>
            @endif
        @endif
    </div>
</x-layouts::admin>
