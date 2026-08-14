# Struktur Folder `resources/views`

Panduan kerangka tampilan FitMate. Dokumen ini menjelaskan **di mana file baru harus diletakkan** dan **kenapa** strukturnya dibagi seperti ini, supaya semua orang menaruh file di tempat yang sama.

**Stack tampilan:** Blade (Laravel 13) · Alpine.js 3 · Tailwind CSS 4
**Acuan tugas:** [`todo.md`](./todo.md) · **Acuan skema:** [`migrationDocs.md`](./migrationDocs.md)

---

## 1. Aturan Utama

Tiga folder, tiga peran yang tidak boleh tertukar:

| Folder | Isi | Cara pakai | Boleh punya data sendiri? |
|---|---|---|---|
| `components/` | Potongan UI yang **dipakai berulang** dan **menerima parameter** | `<x-ui.button>` | Tidak. Semua data masuk lewat `@props` |
| `partials/` | Potongan halaman **tetap** yang dipanggil layout (navbar, footer) | `@include('partials.navbar')` | Tidak. Ikut variabel pemanggilnya |
| Folder fitur (`products/`, `cart/`, …) | **Halaman** yang dikembalikan controller | `return view('products.index')` | Ya. Menerima data dari controller |

Aturan yang menentukan pilihan: **kalau butuh parameter, jadikan component. Kalau isinya selalu sama, jadikan partial. Kalau dipanggil `view()` dari controller, jadikan halaman.**

---

## 2. Pohon Folder

```text
resources/views/
├── components/                      # Komponen <x-...>, semuanya anonymous component
│   ├── layouts/                     # Kerangka halaman (<html> sampai </html>)
│   │   ├── app.blade.php            # Halaman publik & pembeli — navbar + footer
│   │   ├── auth.blade.php           # Login/register — kartu di tengah layar
│   │   └── dashboard.blade.php      # Seller & admin — sidebar + topbar
│   ├── ui/                          # Komponen umum, tidak tahu soal FitMate
│   │   ├── alert.blade.php          # Notifikasi, bisa ditutup (Alpine)
│   │   ├── badge.blade.php
│   │   ├── button.blade.php         # Jadi <a> otomatis kalau diberi href
│   │   ├── card.blade.php           # Slot: heading, footer
│   │   ├── dropdown.blade.php       # Slot: trigger (Alpine)
│   │   ├── dropdown-item.blade.php
│   │   ├── empty-state.blade.php    # Tampilan "belum ada data"
│   │   ├── input.blade.php          # Otomatis isi old() & tandai error
│   │   ├── modal.blade.php          # Dibuka lewat $dispatch (Alpine)
│   │   ├── nav-link.blade.php       # Punya state aktif
│   │   ├── page-header.blade.php    # Judul halaman + slot actions
│   │   ├── select.blade.php
│   │   └── textarea.blade.php
│   ├── form/                        # Perekat form
│   │   ├── field.blade.php          # label + input + hint + pesan error
│   │   └── error.blade.php
│   └── product/                     # Komponen khusus domain FitMate
│       ├── card.blade.php           # Kartu produk di katalog
│       └── fit-badge.blade.php      # Label fit_status: pas / sempit / longgar
│
├── partials/                        # Potongan tetap, dipanggil layout
│   ├── head.blade.php               # <head>, @vite, stack meta & styles
│   ├── navbar.blade.php
│   ├── footer.blade.php
│   ├── sidebar.blade.php            # Menu seller/admin, dipilih lewat $area
│   └── flash.blade.php              # session('success'|'error'|'warning') + $errors
│
├── home.blade.php                   # Beranda
│
├── auth/                            # Fase 4 — Auth & Profil
│   ├── login.blade.php
│   ├── register.blade.php
│   ├── forgot-password.blade.php
│   └── reset-password.blade.php
│
├── products/                        # Fase 6 — Katalog & belanja
│   ├── index.blade.php              # Daftar produk + filter
│   ├── show.blade.php               # Detail produk — layar utama FitMate
│   └── partials/                    # Partial yang hanya dipakai halaman produk
│       ├── filters.blade.php
│       └── size-recommendation.blade.php
│
├── cart/index.blade.php
├── wishlist/index.blade.php
├── checkout/
│   ├── index.blade.php
│   └── success.blade.php
├── orders/
│   ├── index.blade.php
│   └── show.blade.php
├── profile/
│   ├── edit.blade.php               # Data akun
│   ├── body-profile.blade.php       # Ukuran badan — sumber rekomendasi
│   └── addresses.blade.php
│
├── seller/                          # Fase 5 — pakai layout dashboard, area="seller"
│   ├── dashboard.blade.php
│   ├── products/{index,form}.blade.php
│   ├── orders/index.blade.php
│   └── store/edit.blade.php
│
├── admin/                           # pakai layout dashboard, area="admin"
│   ├── dashboard.blade.php
│   ├── size-charts/index.blade.php  # Kelola standar ukuran (BE-037)
│   └── categories/index.blade.php
│
├── errors/{403,404,500}.blade.php   # Dikenali Laravel otomatis, jangan diganti namanya
└── welcome.blade.php                # Bawaan Laravel, sudah tidak dipakai — boleh dihapus
```

---

## 3. Aturan Penamaan

Nama file mengikuti aksi RESTful supaya nama route, controller, dan view sejalan:

| View | Route | Controller |
|---|---|---|
| `products/index.blade.php` | `products.index` | `ProductController@index` |
| `products/show.blade.php` | `products.show` | `ProductController@show` |
| `seller/products/form.blade.php` | `seller.products.create` / `.edit` | satu form dipakai create & edit |

- Nama file: **kebab-case** (`body-profile.blade.php`, bukan `bodyProfile`).
- Satu folder per bagian aplikasi. Kalau satu folder isinya sudah lebih dari ~6 halaman, pecah jadi subfolder.
- Partial yang **hanya dipakai satu halaman** taruh di `nama-fitur/partials/`, bukan di `partials/` global.

---

## 4. Cara Memakai Layout

Layout adalah component, jadi dipakai dengan tag pembuka dan penutup — bukan `@extends`.

```blade
{{-- Halaman pembeli --}}
<x-layouts.app title="Katalog">
    <x-ui.page-header title="Katalog" subtitle="Semua produk dari seluruh toko." />

    {{-- isi halaman di sini --}}
</x-layouts.app>

{{-- Halaman seller / admin --}}
<x-layouts.dashboard title="Produk" area="seller">   {{-- area: seller | admin --}}
    ...
</x-layouts.dashboard>

{{-- Halaman auth --}}
<x-layouts.auth title="Masuk" heading="Masuk ke FitMate" subheading="...">
    ...
</x-layouts.auth>
```

`title` mengisi `<title>` browser (otomatis ditambah `— FitMate`). `area` di layout dashboard menentukan menu sidebar mana yang tampil.

Untuk menyisipkan script atau style khusus satu halaman:

```blade
@push('scripts')
    <script>/* ... */</script>
@endpush
```

---

## 5. Cara Memakai Component

Semuanya *anonymous component* — tidak ada class PHP-nya, cukup satu file Blade.

```blade
<x-ui.button :href="route('products.index')">Lihat Katalog</x-ui.button>
<x-ui.button type="submit" variant="danger" size="sm">Hapus</x-ui.button>

<x-ui.card heading="Ringkasan">
    Isi kartu.

    <x-slot:footer>
        <x-ui.button class="w-full">Checkout</x-ui.button>
    </x-slot>
</x-ui.card>

<x-form.field name="email" label="Email" required>
    <x-ui.input name="email" type="email" />
</x-form.field>
```

Yang perlu diingat:

- **Prop vs atribut.** Nama yang terdaftar di `@props` menjadi variabel; sisanya diteruskan ke elemen HTML. Jadi `class="mt-4"` pada `<x-ui.button>` otomatis digabung dengan class bawaannya.
- **`<x-form.field>` sudah menampilkan pesan error** dari `$errors` berdasarkan `name`. Jangan tulis `@error` lagi secara manual.
- **`<x-ui.input>` sudah memanggil `old()`** dan memberi ring merah kalau field-nya error. Cukup isi `name`.
- Membuat component baru: `php artisan make:component ui/tabs --view`.

### Membuat component baru — kapan?

Buat component kalau markup yang sama muncul di **tiga tempat atau lebih**, atau kalau markup itu punya logika tampilan sendiri (seperti `fit-badge` yang menerjemahkan `fit_status`). Di bawah itu, biarkan saja markup-nya di halaman — component yang terlalu dini justru menyulitkan.

---

## 6. Alpine.js

Alpine dimuat global di `resources/js/app.js`, jadi langsung bisa dipakai di Blade mana pun tanpa import.

```blade
<div x-data="{ open: false }">
    <button x-on:click="open = ! open">Filter</button>
    <div x-show="open" x-cloak>...</div>
</div>
```

Aturan yang berlaku di repo ini:

1. **Selalu pasang `x-cloak`** pada elemen dengan `x-show`, supaya tidak berkedip sebelum Alpine siap. CSS-nya sudah disiapkan di `resources/css/app.css`.
2. **Gunakan sintaks panjang** (`x-on:click`, `x-bind:class`), bukan `@click` / `:class`. Tanda `@` bentrok dengan direktif Blade.
3. **Jangan taruh logika bisnis di Alpine.** Alpine hanya untuk urusan tampilan: buka/tutup, tab, jumlah barang. Perhitungan harga, stok, dan rekomendasi ukuran tetap di server.
4. Modal dibuka lewat event, bukan variabel bersama:
   ```blade
   <x-ui.button x-on:click="$dispatch('open-modal', 'size-chart')">Tabel Ukuran</x-ui.button>
   <x-ui.modal name="size-chart" title="Tabel Ukuran">...</x-ui.modal>
   ```

Kalau butuh plugin Alpine (`@alpinejs/collapse`, `focus`, dll), itu menambah dependensi — minta persetujuan dulu sesuai `CLAUDE.md`.

---

## 7. Tailwind CSS

Tailwind 4 dikonfigurasi lewat CSS, **bukan** `tailwind.config.js`. Semua token ada di blok `@theme` di `resources/css/app.css`.

Warna merek sudah didefinisikan sebagai `brand-50` … `brand-700`:

```blade
<button class="bg-brand-600 text-white hover:bg-brand-700">Simpan</button>
```

Aturan yang berlaku:

- **Pakai warna `brand-*` untuk aksen**, jangan `indigo-*` atau `blue-*` langsung. Kalau warnanya ganti, cukup ubah satu tempat.
- **Jangan menulis CSS sendiri** kecuali benar-benar tidak ada utility-nya. Kalau harus, taruh di `app.css`, bukan `<style>` di Blade.
- **Jangan menyusun nama class dari variabel PHP** (`"bg-{$color}-600"`) — Tailwind memindai file secara statis, class itu tidak akan ikut ter-generate. Pakai peta array yang berisi nama class utuh, seperti di `components/ui/button.blade.php`.
- Susun class dengan urutan: layout → ukuran → warna → state. Contoh: `flex items-center gap-2 px-4 py-2 text-sm bg-brand-600 hover:bg-brand-700`.
- Mode gelap **belum** dipakai. Kalau nanti diperlukan, tambahkan sekaligus di seluruh component, jangan sebagian.

Setelah mengubah tampilan, jalankan `npm run dev` (mode kerja) atau `npm run build` (sebelum commit). Kalau muncul `Unable to locate file in Vite manifest`, artinya build belum dijalankan.

---

## 8. Route Sementara

`routes/web.php` saat ini berisi **route placeholder** — semuanya `Route::view()` yang hanya menampilkan halaman kosong, supaya struktur ini bisa langsung dibuka di browser:

```powershell
php artisan serve
npm run dev
```

Route POST/PATCH-nya sengaja mengembalikan `501 Belum diimplementasikan`. Ganti satu per satu dengan controller sungguhan sesuai urutan di `todo.md`. Nama route-nya sudah dipakai di seluruh view, jadi **pertahankan nama route-nya** (`products.index`, `profile.body`, dst.) agar tidak ada tautan yang rusak.

---

## 9. Menandai Pekerjaan yang Belum Selesai

Setiap tempat yang masih kosong ditandai komentar Blade berisi nomor tugas:

```blade
{{-- TODO(BE-060): ganti contoh statis ini dengan @foreach ($products as $product). --}}
```

Cari semua yang tersisa dengan:

```powershell
Select-String -Path resources/views -Pattern "TODO\(BE-" -Recurse
```

Komentar `{{-- --}}` tidak ikut terkirim ke browser, jadi aman ditinggal sampai bagian itu dikerjakan.

---

## 10. Test

`tests/Feature/ViewSmokeTest.php` merender seluruh halaman dan memastikan semuanya balas `200`. Ini menangkap nama route yang salah ketik, component yang belum ada, dan prop wajib yang lupa diisi — hal-hal yang tidak ketahuan saat Blade dikompilasi.

```powershell
php artisan test --compact --filter=ViewSmokeTest
```

**Setiap menambah halaman baru, tambahkan nama route-nya ke daftar di test itu.**

---

## 11. Daftar Periksa Sebelum Commit

1. `php artisan test --compact` hijau
2. `npm run build` berhasil
3. Halaman baru sudah masuk `ViewSmokeTest`
4. Tidak ada class Tailwind yang disusun dari variabel PHP
5. Elemen `x-show` sudah punya `x-cloak`
6. Markup berulang sudah diangkat jadi component, bukan disalin-tempel
