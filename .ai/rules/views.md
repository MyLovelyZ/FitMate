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
