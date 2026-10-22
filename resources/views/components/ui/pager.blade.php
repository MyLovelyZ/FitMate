{{--
    Navigasi sebelumnya/berikutnya untuk komponen Livewire yang memakai
    `WithPagination`. Tombolnya memanggil `previousPage`/`nextPage` milik komponen induk.
--}}
@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="mt-6 flex items-center justify-between gap-4 text-[12.5px]" aria-label="Halaman">
        <button
            type="button"
            wire:click="previousPage"
            @disabled($paginator->onFirstPage())
            class="cursor-pointer rounded-[2px] border border-line px-4 py-2 font-semibold transition-colors duration-150 hover:border-ink disabled:cursor-not-allowed disabled:text-muted disabled:hover:border-line"
        >
            Sebelumnya
        </button>

        <span class="text-muted">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>

        <button
            type="button"
            wire:click="nextPage"
            @disabled(! $paginator->hasMorePages())
            class="cursor-pointer rounded-[2px] border border-line px-4 py-2 font-semibold transition-colors duration-150 hover:border-ink disabled:cursor-not-allowed disabled:text-muted disabled:hover:border-line"
        >
            Berikutnya
        </button>
    </nav>
@endif
