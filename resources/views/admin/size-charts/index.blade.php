<x-layouts.dashboard title="Standar Ukuran" area="admin">
    <x-ui.page-header title="Standar Ukuran FitMate" subtitle="Angka di sini menentukan akurasi seluruh rekomendasi ukuran.">
        <x-slot:actions>
            <x-ui.button size="sm">Tambah Chart</x-ui.button>
        </x-slot>
    </x-ui.page-header>

    {{--
        TODO(BE-037): CRUD size_charts + entries + rentang tiap dimensi.
        Dijaga SizeChartPolicy (BE-033): hanya admin dan superadmin.
        TODO(BE-035): peringatkan bila rentang antar entry tumpang tindih atau ada celah.
    --}}
    <x-ui.empty-state title="Belum ada standar ukuran" description="Menunggu keputusan KEP-1: angka acuan S/M/L/XL/XXL." />
</x-layouts.dashboard>
