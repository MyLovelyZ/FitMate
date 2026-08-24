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
- Tailwind 4 tanpa `tailwind.config.js` — token ada di blok `@theme` di `resources/css/app.css`. Pakai `brand-*` untuk warna aksen. Jangan pernah menyusun nama class dari variabel PHP; pakai peta array berisi nama class utuh.
- `tests/Feature/ViewSmokeTest.php` merender semua halaman. Setiap halaman baru wajib ditambahkan nama route-nya ke daftar di test itu.
- Route di `routes/web.php` masih `Route::view()` placeholder; nama route sudah dipakai di seluruh view, jadi pertahankan namanya saat diganti controller.

## Halaman adalah komponen Livewire full-page, bukan Blade view biasa
Setiap halaman = single-file component di `resources/views/pages/⚡nama.blade.php`, dirender lewat `Route::livewire('/path', 'pages::nama')`. Jangan pakai `Route::view()` atau controller yang me-return view.

Layout ada di `resources/views/layouts/` dan dipanggil lewat namespace Livewire: default `layouts::app`, halaman auth pakai `#[Layout('layouts::auth')]`. Bukan `<x-layouts.app>`.

Alpine sudah dibundel Livewire 4 — jangan import `alpinejs` di `resources/js/app.js`, nanti Alpine ke-register dua kali.

`resources/views/components/**` tetap komponen Blade biasa (`<x-ui.input>`), dipakai di dalam komponen Livewire.

## Dua set token warna: Estética (publik) vs navy (auth)
Layout publik (`layouts/app`, `partials/navbar`, `pages/⚡home`) memakai palet Estética Archive: `bg`, `surface`, `ink`, `muted`, `line`, `cream`, `sale`, plus `font-display` (DM Serif Display) dan spacing `side`/`header` (`px-side`, `h-header`, `top-header`). Semua didefinisikan di blok `@theme` `resources/css/app.css`.

Token lama `navy-*`, `abu-*`, `brand-*` masih dipakai `layouts/auth` beserta halaman login/register — jangan dihapus. Jangan mencampur dua set itu dalam satu halaman.

Font di-self-host lewat `bunny()` di `vite.config.js`, bukan `<link>` Google Fonts di `partials/head`. Tambah bobot baru di situ.
