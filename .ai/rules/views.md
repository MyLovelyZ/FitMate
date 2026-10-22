---
paths:
  - 'resources/views/**'
---

# Views

## Struktur dan konvensi Blade FitMate
Kerangka lengkap dijelaskan di `viewsstructure.md` (root repo) — baca itu sebelum menambah view.

- Layout adalah component: `<x-layouts.app>`, `<x-layouts.auth>`, `<x-layouts.dashboard area="seller|admin">`. Jangan pakai `@extends`.
- `components/` = potongan UI berparameter (`@props`), `partials/` = potongan tetap yang di-`@include` layout, folder fitur = halaman yang dikembalikan controller. Partial khusus satu halaman taruh di `<fitur>/partials/`.
- Alpine: selalu sintaks panjang (`x-on:click`, `x-bind:class`), bukan `@click`/`:class`, karena `@` bentrok dengan Blade. Elemen `x-show` wajib punya `x-cloak`.
- Tailwind 4 tanpa `tailwind.config.js` — token ada di blok `@theme` di `resources/css/app.css`. Pakai token Estética (lihat di bawah) untuk warna. Jangan pernah menyusun nama class dari variabel PHP; pakai peta array berisi nama class utuh.
- `tests/Feature/ViewSmokeTest.php` merender semua halaman. Setiap halaman baru wajib ditambahkan nama route-nya ke daftar di test itu.
- Route di `routes/web.php` masih `Route::view()` placeholder; nama route sudah dipakai di seluruh view, jadi pertahankan namanya saat diganti controller.

## Halaman adalah komponen Livewire full-page, bukan Blade view biasa
Setiap halaman = single-file component di `resources/views/pages/⚡nama.blade.php`, dirender lewat `Route::livewire('/path', 'pages::nama')`. Jangan pakai `Route::view()` atau controller yang me-return view.

Layout ada di `resources/views/layouts/` dan dipanggil lewat namespace Livewire: default `layouts::app`, halaman auth pakai `#[Layout('layouts::auth')]`. Bukan `<x-layouts.app>`.

Alpine sudah dibundel Livewire 4 — jangan import `alpinejs` di `resources/js/app.js`, nanti Alpine ke-register dua kali.

`resources/views/components/**` tetap komponen Blade biasa (`<x-ui.input>`), dipakai di dalam komponen Livewire.

## Satu set token warna: Estética Archive
Semua layout (`layouts/app`, `layouts/auth`) dan halaman, termasuk login/register beserta `x-ui.input`/`x-ui.button`, memakai palet Estética Archive: `bg`, `surface`, `ink`, `muted`, `line`, `cream`, `sale`, `success`, plus `font-display` (DM Serif Display) dan spacing `side`/`header` (`px-side`, `h-header`, `top-header`). Semua didefinisikan di blok `@theme` `resources/css/app.css`.

Token lama `navy-*`, `abu-*`, `brand-*` sudah tidak dipakai view mana pun dan tidak ikut mode gelap — jangan dipakai di kode baru.

Font di-self-host lewat `bunny()` di `vite.config.js`, bukan `<link>` Google Fonts di `partials/head`. Tambah bobot baru di situ.

## Dark mode = token Estética ditimpa lewat <html data-theme>
Tema disimpan di `users.theme` (light|dark|system, pilihan di tab "Tampilan" profil) dan dirender ke `<html data-theme="{{ App\Models\User::themeFor(auth()->user()) }}">` di KEDUA layout. `system` ditangani CSS (prefers-color-scheme), jadi tidak ada script anti-kedip. Jangan set tema lewat class `dark` via JS: wire:navigate menimpa atribut `<html>` dengan versi server.

Mode gelap bekerja dengan menimpa token (`bg`, `surface`, `ink`, `muted`, `line`, `cream`, `sale`, `success`) di `resources/css/app.css`, jadi view cukup pakai token itu — tanpa `dark:` per elemen, dan jangan hardcode hex/`bg-white`. `bg-ink text-cream` otomatis jadi tombol terang. Elemen di atas foto yang harus tetap krem (hero home) pakai utility `on-photo`.

## Foto produk selalu jadi link ke halaman produk; link #anchor tanpa wire:navigate
Thumbnail produk di daftar (keranjang, checkout, pesanan, produk seller) pakai `<x-ui.product-thumb :product="..." class="h-.. w-.. rounded-.." />`: otomatis link ke `products.show` selama `isPurchasable()`, dan jadi `<div>` biasa kalau produknya sudah tidak dijual (halaman detailnya 404). Grid katalog pakai `x-ui.product-card`. Jangan render `<img>` produk polos di luar link.

app.css membuat `a img { pointer-events: none }` dan app.js memblok dragstart/klik-kanan di foto, supaya menyentuh foto membuka link-nya, bukan menu "Download gambar". Link ke `#anchor` di halaman lain (mis. `route('home').'#ethos'`, `route('help').'#kontak'`) jangan diberi `wire:navigate` — Livewire tidak menggulir ke anchor setelah navigasi.
