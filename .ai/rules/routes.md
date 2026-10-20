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

## Area per role: middleware alias, nama route, dan halaman datar
Nilai role sah hanya enum `users.role`: user, seller, customerservice, admin, superadmin (bukan 'cs'/'super_admin'). Alias middleware di `bootstrap/app.php` harus sama persis dengan yang dipakai di route (`superadmin`, bukan `super_admin`).

Halaman area role tetap komponen datar `pages::<area>-<halaman>` (mis. `pages::seller-orders`), bukan subfolder `pages::seller.orders.index`. Prefix `->as('seller.')` tanpa spasi, dan `->name()` di dalam grup jangan mengulang prefix (`orders`, bukan `user.orders`).

Login & register ditangani komponen Livewire `pages::login`/`pages::register` — jangan tambah LoginController/RegisterController. Tujuan setelah login per role ada di `User::dashboardRouteName()`. Halaman yang butuh login dites di `tests/Feature/RoleAreaTest.php`, bukan ViewSmokeTest.

## Profil satu halaman untuk semua role: route `profile`
Halaman profil adalah `pages::profile` di `/profil` (nama route `profile`, middleware `auth` saja) dan dipakai SEMUA role — jangan buat profil terpisah per area. Tab ukuran badan & alamat hanya untuk role `user`. Menu akun di navbar dan tab area pembeli diambil dari `User::accountLinks()`; route `user.profile` sudah dihapus.
