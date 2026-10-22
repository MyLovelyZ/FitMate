---
paths:
  - 'resources/views/pages/**'
---

# Pages

## Filter yang disimpan di URL harus dirender ulang ke elemennya
`wire:model` tidak menulis `value`/`selected` ke HTML yang dikirim server. Jadi halaman yang menyimpan state di query string lewat `#[Url]` (mis. filter katalog di `⚡catalog`) harus menulis nilainya sendiri: `value="{{ $minPrice }}"` pada input dan `@selected($sort === $value)` pada `<option>`.

Tanpa itu, URL yang dibagikan memang menghasilkan daftar produk yang benar, tapi kotak filternya tampil kosong/salah sampai Livewire selesai dimuat.

## Menu katalog (Men/Women/Accessories/Sale/New) = query string #[Url] katalog
Link navbar dan kartu kategori di home membuka `route('products.index', [...])` dengan kunci `gender` (men|women), `type` (slug category_types, mis. aksesoris), `collection` (new|sale). Kuncinya harus sama dengan properti `#[Url]` di `⚡catalog`; nilai tak dikenal di-reset di `mount()`.

Produk `gender = unisex` sengaja ikut tampil di Men dan Women. "On Sale" = `compare_at_price > base_price` (harga coret), bukan kolom flag. "New Arrivals" = 12 produk aktif terbaru, bukan rentang tanggal, supaya data demo lama tidak bikin menunya kosong.

## UX halaman: breadcrumbs, loading, lazy island, notifikasi
- Breadcrumbs: `<x-ui.breadcrumbs :items="['Beranda' => route('home'), ..., 'Halaman Ini' => null]" />`, atau prop `breadcrumbs` di `x-ui.area-header`. Item terakhir = halaman aktif (URL null). Jangan tambah link "Back" lagi.
- Loading: bar global di `partials/loading-bar` + `resources/js/app.js` sudah menangkap semua request Livewire; daftar/tabel cukup diberi `wire:loading.delay.class="pointer-events-none opacity-50"`; tombol simpan pakai `x-ui.save-button target="namaAction"`. Link internal wajib `wire:navigate` supaya progress bar navigasi muncul.
- Lazy: angka agregat dashboard dibungkus `@island(name: '...', lazy: true)` + `@placeholder` berisi `x-ui.skeleton`. Di test, render island lewat helper `loadIsland($component, 'nama')` di tests/Pest.php — `Livewire::withoutLazyLoading()` tidak berlaku untuk island.
- Feedback sukses: `$this->dispatch('notify', message: '...')` → toast di `partials/toast`.

## Pencarian navbar = query `q` di katalog
Kolom cari di `partials/navbar` membuka `route('products.index', ['q' => ...])` (lewat `Livewire.navigate`, atau GET biasa tanpa JS). `⚡catalog` menyimpannya di `#[Url(as: 'q')] $search`, dirapikan + dipotong 100 karakter di `mount()`.

Kata kunci dipecah per kata (maks 5); setiap kata cukup cocok dengan nama produk, nama kategori, ATAU nama jenis kategori. Deskripsi sengaja tidak dicari karena semua deskripsi demo memuat "FitMate Archive" sehingga hasilnya jadi semua produk. `resetFilters()` ikut menghapus `search`.
