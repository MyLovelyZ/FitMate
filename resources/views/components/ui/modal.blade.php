@props([
    'name',
    'title' => null,
    'maxWidth' => 'max-w-lg',
])

{{--
    Dibuka dari mana saja dengan Alpine:
    <x-ui.button x-on:click="$dispatch('open-modal', 'size-chart')">Tabel Ukuran</x-ui.button>
--}}
<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
>
    <div class="fixed inset-0 bg-gray-900/50" x-show="open" x-transition.opacity x-on:click="open = false"></div>

    <div class="relative w-full {{ $maxWidth }} rounded-xl bg-white shadow-xl" x-show="open" x-transition>
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
            <h2 class="font-semibold text-gray-900">{{ $title }}</h2>

            <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100" x-on:click="open = false">
                <span class="sr-only">Tutup</span>
                &times;
            </button>
        </div>

        <div class="max-h-[70vh] overflow-y-auto p-5">
            {{ $slot }}
        </div>
    </div>
</div>
