---
paths:
  - routes/web.php
---

# Routes

## Path route bahasa Indonesia, nama route RESTful Inggris
Semua halaman didaftarkan dengan `Route::livewire()` ke komponen full-page `pages::*` — bukan `Route::view()` (catatan lama di `.ai/rules/views.md` sudah tidak berlaku).

Tiga nama yang sengaja tidak harus sama:
- Path URL pakai bahasa Indonesia: `/produk/{product:slug}`.
- Nama route pakai konvensi RESTful Inggris seperti tabel di `Docs/viewsstructure.md` bagian 3: `products.show`. Nama inilah yang dipakai `route()` di seluruh view, jadi jangan diganti.
- Nama file komponen tetap datar di `resources/views/pages/⚡<halaman>.blade.php` (`⚡detail-product`), tanpa subfolder.

Route model binding pakai `{model:slug}` karena slug sudah unique di semua tabel katalog; komponennya cukup mendeklarasikan `public Product $product`.
