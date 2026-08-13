# FitMate Backend — Todo List

Backlog tim backend. Skema database sudah final dan sudah jalan; dokumen ini isinya semua yang harus dibangun di atasnya.

**Acuan skema:** [`migrationDocs.md`](./migrationDocs.md)
**Stack:** Laravel 13.24 · PHP 8.4 · Sanctum · Pest 5 · SQLite (dev)

---

## Status Saat Ini

| Bagian | Status |
|---|---|
| Migration (28 tabel) | ✅ Selesai, sudah dijalankan |
| Model | 🟡 2 dari 28 (`User`, `UserAddress`) |
| Enum | ❌ Belum ada |
| Factory | 🟡 1 (`UserFactory`) |
| Seeder | 🟡 Masih default |
| Controller | ❌ Belum ada (cuma `Controller.php` stub) |
| Route API | ❌ Masih default `/user` |
| Policy | ❌ Belum ada |
| Test | ❌ Masih `ExampleTest` bawaan |

---

## Cara Pakai Dokumen Ini

**Format ID:** `BE-xxx`. Jangan dipakai ulang kalau tugasnya dibatalkan.

**Ukuran (kompleksitas relatif, bukan estimasi waktu):**
`S` = jelas, sekali duduk · `M` = perlu mikir desainnya · `L` = perlu didiskusikan dulu sebelum dikerjakan

**Penanda:**
🔴 Jalur kritis — menahan tugas lain
🔒 Terblokir — tunggu keputusan atau tugas lain
🧪 Wajib ada test sebelum dianggap selesai

---

## ⚠️ Keputusan yang Menunggu (untuk PM / Tech Lead)

Ini bukan tugas developer. Tim akan berhenti kalau belum diputuskan.

- [ ] **KEP-1 🔴 Angka standar ukuran FitMate.** Berapa rentang cm untuk S/M/L/XL/XXL di tiap chart? Ini keputusan bisnis, bukan teknis. Saran: pakai acuan SNI atau standar ukuran Asia, jangan karang sendiri. **Menahan `BE-030`, dan `BE-030` menahan seluruh fitur inti.**
- [ ] **KEP-2** Nomor sepatu pakai sistem apa? Dokumentasi mengasumsikan EU (38–45). Konfirmasi.
- [ ] **KEP-3** Payment gateway mana? Midtrans, Xendit, atau manual transfer dulu? Menentukan `BE-072`.
- [ ] **KEP-4** Ongkir dihitung pakai API kurir (RajaOngkir/Biteship) atau tarif flat dulu? Menentukan `BE-071`.
- [ ] **KEP-5** Seller daftar sendiri lalu diverifikasi admin, atau didaftarkan admin? Menentukan `BE-050`.
- [ ] **KEP-6** Perlu fitur payout ke seller di rilis pertama, atau settlement manual dulu? Kalau perlu, backlog ini harus ditambah ~5 tugas.

---

## Jalur Kritis

Urutan yang menentukan kapan fitur inti bisa didemokan. Semua yang di luar jalur ini bisa dikerjakan paralel.

```
KEP-1 (keputusan angka standar)
  └─> BE-003 (enum)
        └─> BE-010..BE-016 (model)
              └─> BE-030 (SizeChartSeeder)
                    └─> BE-031 (SizeRecommendationService)  ← fitur inti FitMate
                          └─> BE-034 (validasi integritas ukuran)
                                └─> BE-062 (rekomendasi di halaman produk)
```

---

## Fase 0 — Fondasi

Kerjakan duluan. Semua fase lain menumpuk di atas ini.

- [ ] **BE-001 🔴 `S` Seragamkan konvensi model.**
  `User` pakai atribut `#[Fillable]` + metode `casts()`, `UserAddress` pakai properti `$fillable` + `$casts`. Pilih satu: **properti `$fillable` + metode `casts(): array`**. Tambahkan return type di semua metode relasi (diwajibkan `CLAUDE.md`, dua model yang ada belum memenuhi).

- [ ] **BE-002 `S` Beresin `UserAddress`.**
  Ada cast `latitude` dan `longitude` tapi kolomnya tidak ada di migration — cast-nya tidak berefek. Putuskan: hapus cast, atau tambah kolom lewat migration baru.

- [ ] **BE-003 🔴 `M` Buat 17 enum di `app/Enums/`.**
  Backed enum string, kunci TitleCase. Daftar lengkap ada di `migrationDocs.md` bagian 7. **Nilainya harus sama persis dengan definisi enum di migration** — kalau meleset, cast-nya error saat runtime.

- [ ] **BE-004 `M` Tentukan struktur & kontrak API.**
  Versioning `/api/v1`, format response sukses/error yang konsisten, konvensi paginasi, penanganan exception terpusat. Tulis hasilnya jadi rules supaya tim tidak jalan sendiri-sendiri.

- [ ] **BE-005 `S` Buat `.ai/rules` untuk konvensi tim.**
  Repo belum punya. Isi hasil keputusan BE-001 dan BE-004 supaya anggota baru dan AI agent ikut aturan yang sama.

- [ ] **BE-006 `S` Pastikan Pint jalan sebelum commit.**
  Tambahkan ke CI atau pre-commit hook. Aturannya: `vendor/bin/pint --dirty`.

- [ ] **BE-007 `S` Bersihkan test bawaan.**
  Hapus `ExampleTest`, siapkan `RefreshDatabase` di `tests/Pest.php`, pastikan `php artisan test` hijau.

---

## Fase 1 — Model & Factory

🔒 Terblokir `BE-003`.

Detail fillable, casts, dan relasi tiap model ada di `migrationDocs.md` bagian 6. Pakai `php artisan make:model NamaModel -f --no-interaction` (tanpa `-m`, migration sudah ada).

- [ ] **BE-010 🔴 `M` Model pengguna.**
  `UserBodyProfile`, `UserBodyMeasurement`. Tambahkan relasi baru di `User` (`bodyProfiles`, `defaultBodyProfile`, `store`, `cart`, `orders`, `wishlists`, `reviews`) dan cast `gender`/`role`/`status` ke enum.
  Logika khusus: saat satu profil di-set `is_default`, profil lain milik user yang sama harus jadi `false`.

- [ ] **BE-011 🔴 `M` Model mesin ukuran.**
  `BodyMeasurement`, `SizeChart`, `SizeChartEntry`, `SizeChartEntryMeasurement`, `SizeRecommendation`.
  Scope yang dibutuhkan: `BodyMeasurement::forSizeType()` (ambil yang `applies_to` cocok **atau** `general`), `SizeChart::matching(SizeType, Gender)`.

- [ ] **BE-012 🔴 `S` Model katalog dasar.**
  `Category` (relasi `parent`/`children`, helper `requiresSizing()`), `Brand`, `Store` (+ `SoftDeletes`).

- [ ] **BE-013 🔴 `M` Model produk.**
  `Product` (+ `SoftDeletes`), `ProductVariant`, `ProductImage`, `ProductMeasurement`.
  Jangan masukkan `rating_average`, `rating_count`, `sold_count`, `view_count` ke `$fillable` — itu counter yang diisi sistem.

- [ ] **BE-014 `S` Model keranjang.** `Cart`, `CartItem`, `Wishlist`.

- [ ] **BE-015 `M` Model transaksi.**
  `Order`, `StoreOrder`, `OrderItem`, `Payment`, `Shipment`.
  `Payment::$payload` **wajib** masuk `$hidden` — isinya respon mentah provider dan bisa mengandung data sensitif.

- [ ] **BE-016 `S` Model sisanya.** `ProductReview`, `Coupon`, `CouponUsage`, `BannerPromo`.

- [ ] **BE-017 `M` Factory untuk semua model.**
  State yang dibutuhkan: `UserFactory::seller()/admin()`, `StoreFactory::active()`, `ProductFactory::active()/withoutSizing()`, `ProductVariantFactory::outOfStock()`, `OrderFactory::paid()`.
  ⚠️ `size_charts` punya `unique(['size_type','gender'])` — factory-nya tidak boleh mengacak kombinasi, pakai state eksplisit atau `firstOrCreate`.

- [ ] **BE-018 🧪 `S` Test arsitektur.**
  Pakai Pest `arch()`: pastikan semua model punya return type di relasi, tidak ada `dd()`/`dump()` tertinggal, semua enum backed string.

---

## Fase 2 — Data Acuan

🔒 Terblokir `BE-011`. `BE-030` juga terblokir `KEP-1`.

- [ ] **BE-020 🔴 `S` `BodyMeasurementSeeder`.**
  10 dimensi, daftar lengkapnya ada di `migrationDocs.md` bagian 8. `key` di sini dipakai di seluruh kode — sekali dirilis, susah diubah. Review namanya baik-baik sebelum merge.

- [ ] **BE-021 `M` `CategorySeeder`.**
  Kategori bertingkat beserta `size_type` yang benar. Pemetaannya: pakaian→`top`, celana→`bottom`, sepatu & sandal→`footwear`, topi/kacamata/jam/kalung→`none`.

- [ ] **BE-022 `S` `BrandSeeder`** — brand awal untuk demo.

- [ ] **BE-030 🔴🔒 `L` `SizeChartSeeder` — INI FITUR INTINYA.**
  Terblokir **KEP-1**. Isi: 5 chart (Atasan Pria, Atasan Wanita, Bawahan Pria, Bawahan Wanita, Alas Kaki Unisex), entry per chart dengan `sort_order` berurutan, dan rentang min/max tiap dimensi.
  **Seluruh akurasi produk bergantung ke tabel ini.** Jangan diisi angka asal untuk "sementara" — angka sementara punya kebiasaan bertahan sampai produksi.

- [ ] **BE-023 `S` Rapikan `DatabaseSeeder`.**
  Panggil seeder acuan berurutan. Pisahkan seeder data acuan (wajib jalan di produksi) dari seeder data contoh (khusus dev).

---

## Fase 3 — Mesin Ukuran

🔒 Terblokir `BE-030`. **Ini pembeda FitMate dari e-commerce biasa. Kualitasnya jangan dikompromikan.**

- [ ] **BE-031 🔴🧪 `L` `SizeRecommendationService`.**
  Input: `UserBodyProfile` + `Product` (atau `SizeChart` langsung). Output: entry yang disarankan + `fit_score` + `fit_status`.
  Query intinya sudah divalidasi jalan, ada di `migrationDocs.md` bagian 5.
  Aturan: `fit_score` = `matched / total × 100`. `fit_status` dari posisi nilai user terhadap rentang — di bawah `min` → `tight`, di atas `max` → `loose`, di dalam → `fit`.
  **Kasus yang wajib ditangani:** user belum isi ukuran sama sekali · baru isi sebagian dimensi · tidak ada entry yang cocok · produk kategori `none` (harus langsung berhenti, jangan hitung).

- [ ] **BE-032 🧪 `M` Simpan hasil ke `size_recommendations`.**
  Pakai `updateOrCreate()` mengikuti unique key `(user_body_profile_id, size_chart_id, product_id)`.
  Invalidasi cache-nya ketika ukuran badan user berubah — kalau lupa, user melihat rekomendasi basi selamanya.

- [ ] **BE-033 🔴 `S` `SizeChartPolicy`.**
  Hanya `admin` dan `superadmin` yang boleh `create`, `update`, `delete`. Berlaku juga untuk `SizeChartEntry` dan `SizeChartEntryMeasurement`.
  Ini yang menegakkan keputusan "standar dikelola terpusat" — skema tidak bisa memaksakannya sendiri.

- [ ] **BE-034 🔴🧪 `M` Validasi integritas ukuran. LUBANG PALING BERBAHAYA.**
  Dua aturan yang harus ditegakkan di kode karena skema tidak bisa menjaminnya:
  1. `products.size_chart_id` harus punya `size_type` yang sama dengan `category->size_type`
  2. `product_variants.size_chart_entry_id` harus milik chart produknya
  Tanpa ini, seller bisa memasang entry "Alas Kaki 42" ke produk kaos, dan seluruh janji standarisasi bocor lewat situ. Buat custom validation rule supaya bisa dipakai ulang.

- [ ] **BE-035 🧪 `M` Validasi rentang chart.**
  `max_value` harus ≥ `min_value`. Peringatkan admin kalau rentang antar entry tumpang tindih atau ada celah (misal L berhenti di 103 tapi XL mulai dari 105 — orang dengan dada 104 tidak dapat rekomendasi).

- [ ] **BE-036 `M` API ukuran badan user.**
  CRUD `user_body_profiles` + pengisian `user_body_measurements`. Form-nya harus dinamis mengikuti isi `body_measurements`, jangan di-hardcode — itu justru alasan kita pakai kamus dimensi.

- [ ] **BE-037 `M` API admin untuk kelola standar.**
  CRUD `size_charts` + entries + rentangnya. Dijaga `BE-033`.

- [ ] **BE-038 🧪 `M` Test menyeluruh mesin ukuran.**
  Skenario: badan pas di tengah rentang · pas di batas (`min` dan `max` persis) · di antara dua ukuran · di luar semua rentang · dimensi tidak lengkap · produk tanpa ukuran.
  **Fitur ini tidak boleh rilis tanpa test.**

---

## Fase 4 — Auth & Profil

Bisa paralel dengan Fase 3.

- [ ] **BE-040 `M` Auth API pakai Sanctum.** Register, login, logout, refresh. Rate limit di endpoint login.
- [ ] **BE-041 `S` Verifikasi email.** Kolom `email_verified_at` sudah ada.
- [ ] **BE-042 `S` Lupa password.** Tabel `password_reset_tokens` sudah ada.
- [ ] **BE-043 `S` CRUD profil user** — termasuk upload foto profil.
- [ ] **BE-044 `M` CRUD alamat user.** Logika `is_default`: set satu jadi default harus mematikan yang lain.
- [ ] **BE-045 `M` Middleware & gate berbasis role.** Untuk `user`, `seller`, `customerservice`, `admin`, `superadmin`.

---

## Fase 5 — Seller & Katalog

🔒 Terblokir `BE-013`, `BE-034`.

- [ ] **BE-050 🔒 `M` Pendaftaran & verifikasi toko.** Terblokir **KEP-5**. Alur status: `pending` → `active`, plus penolakan dan suspend.
- [ ] **BE-051 `S` CRUD profil toko** — logo, banner, alamat asal pengiriman.
- [ ] **BE-052 `L` CRUD produk untuk seller.** Termasuk varian dan gambar. **Wajib lewat validasi `BE-034`.**
- [ ] **BE-053 `M` Upload & kelola gambar produk.** Urutan, penanda `is_primary` (hanya boleh satu per produk).
- [ ] **BE-054 `M` Input ukuran asli produk** (`product_measurements`) per varian. Tampilkan peringatan ke seller kalau angkanya menyimpang jauh dari rentang standar.
- [ ] **BE-055 `M` Kelola stok per varian.** Siapkan untuk kondisi balapan — dua orang checkout barang terakhir bersamaan.
- [ ] **BE-056 `M` Alur persetujuan produk oleh admin.** `draft` → `pending_review` → `active`/`rejected`.
- [ ] **BE-057 `S` CRUD kategori & brand untuk admin.**

---

## Fase 6 — Belanja

🔒 Terblokir `BE-052`.

- [ ] **BE-060 `L` Katalog: daftar, filter, urutkan.**
  Filter: kategori, brand, harga, gender, ukuran, warna, rating.
  ⚠️ Rawan N+1. Pakai eager loading, dan pastikan index `(status, category_id)` kepakai.
- [ ] **BE-061 `M` Pencarian produk.** Mulai dari `LIKE` dulu; kalau lambat baru pertimbangkan Scout.
- [ ] **BE-062 🔴 `M` Detail produk + rekomendasi ukuran.**
  Inilah layar utama FitMate. Tampilkan ukuran yang disarankan, alasannya, dan tabel standar lengkapnya.
- [ ] **BE-063 `S` Penghitung view produk.** Jangan `update()` langsung tiap request — pakai queue atau agregasi berkala.
- [ ] **BE-064 `M` API keranjang.** Tambah, ubah jumlah, hapus. Tampilkan dikelompokkan per toko.
- [ ] **BE-065 `S` API wishlist.**
- [ ] **BE-066 `S` API banner promo.** Filter berdasarkan `placement` dan rentang tanggal aktif.

---

## Fase 7 — Checkout & Transaksi

🔒 Terblokir `BE-064`. Fase paling rawan bug — jangan dikerjakan terburu-buru.

- [ ] **BE-070 🔴🧪 `L` Checkout: pecah keranjang jadi order multi-seller.**
  Satu `orders` → banyak `store_orders` → `order_items`.
  **Wajib dalam transaksi database.** Salin semua data ke `order_items` (nama produk, harga, ukuran, warna) — kalau seller mengubah harga besok, nota lama harus tetap menunjukkan angka yang dulu.
  Isi juga `recommended_size_label` dari hasil rekomendasi saat itu.
  Salin alamat ke kolom `shipping_*` di `orders`, jangan referensi ke `user_addresses`.

- [ ] **BE-071 🔒 `M` Perhitungan ongkir per toko.** Terblokir **KEP-4**.
- [ ] **BE-072 🔒 `L` Integrasi payment gateway.** Terblokir **KEP-3**. Termasuk webhook, dan penanganan pembayaran kedaluwarsa.
- [ ] **BE-073 🧪 `M` Penerapan & validasi kupon.**
  Cek: aktif, dalam rentang tanggal, `min_purchase` terpenuhi, `usage_limit` belum habis, user belum pernah pakai.
  Untuk tipe `percentage`, potongannya dibatasi `max_discount`. Catat ke `coupon_usages`.
- [ ] **BE-074 🧪 `M` Alur status order.**
  Status `store_orders` jalan sendiri-sendiri per toko. Status `orders` induk diturunkan dari gabungan semuanya. Definisikan transisi yang sah dan tolak yang tidak.
- [ ] **BE-075 `M` Pengurangan stok saat checkout.** Kunci baris varian selama transaksi. Kembalikan stok kalau order dibatalkan.
- [ ] **BE-076 `M` Manajemen pengiriman.** Input resi oleh seller, lacak status, tandai terkirim.
- [ ] **BE-077 `M` Dashboard order untuk seller.** Daftar `store_orders` milik tokonya saja — **pastikan seller tidak bisa melihat order toko lain.**
- [ ] **BE-078 `S` Riwayat order untuk pembeli.**
- [ ] **BE-079 `M` Pembatalan & refund.**

---

## Fase 8 — Review & Umpan Balik Ukuran

🔒 Terblokir `BE-074`.

- [ ] **BE-080 `M` Kirim review produk.**
  Hanya boleh untuk `order_item` yang sudah berstatus terkirim. Satu item satu review (sudah dijamin unique constraint).
- [ ] **BE-081 `M` Hitung ulang rating.**
  Observer yang memperbarui `products.rating_*` dan `stores.rating_*` setelah review disimpan atau dihapus. Pakai queue kalau produknya sudah banyak.
- [ ] **BE-082 🔴 `M` Laporan umpan balik ukuran untuk admin.**
  Agregasi `fit_feedback` per kategori dan per chart entry. **Ini yang menutup lingkaran umpan baliknya:** kalau banyak yang melaporkan `too_small` pada ukuran tertentu, admin punya bukti untuk menyetel ulang rentang di `size_chart_entry_measurements` — bukan menebak.
- [ ] **BE-083 `S` Laporan kepatuhan rekomendasi.**
  Bandingkan `order_items.recommended_size_label` dengan `size_label`. Menjawab: seberapa sering user menuruti saran kita, dan apakah yang menurut lebih jarang komplain soal ukuran? Ini metrik yang membuktikan fitur intinya berhasil.

---

## Fase 9 — Pengamanan & Rilis

- [ ] **BE-090 `L` Audit otorisasi menyeluruh.**
  Periksa satu per satu: seller hanya bisa menyentuh datanya sendiri, user hanya bisa melihat ordernya sendiri, hanya admin yang bisa mengubah standar ukuran. **Kerjakan sebelum rilis, bukan sesudah.**
- [ ] **BE-091 `M` Rate limiting.** Terutama login, register, pencarian, dan webhook.
- [ ] **BE-092 `M` Audit N+1 dan index.**
  Nyalakan `Model::preventLazyLoading()` di lingkungan dev. Tinjau ulang index setelah pola query sebenarnya terlihat.
- [ ] **BE-093 `M` Validasi & pembatasan upload file.** Tipe, ukuran, dan pemindaian file gambar.
- [ ] **BE-094 `S` Ganti SQLite ke MySQL/PostgreSQL untuk staging & produksi.**
  Perilaku `enum` dan constraint di SQLite lebih longgar. Jalankan ulang seluruh test suite setelah pindah.
- [ ] **BE-095 `M` Siapkan queue worker.** Untuk hitung ulang rating, kirim email, dan webhook.
- [ ] **BE-096 `M` Logging & pemantauan error.**
- [ ] **BE-097 `L` Dokumentasi API.** Untuk tim frontend/mobile.
- [ ] **BE-098 `M` Lengkapi cakupan test.** Target: seluruh alur uang dan seluruh mesin ukuran tertutup test.

---

## Definisi Selesai

Sebuah tugas baru boleh dicentang kalau:

1. Kode jalan dan sudah dites manual
2. `vendor/bin/pint --dirty` bersih
3. `php artisan test` hijau
4. Tugas bertanda 🧪 punya test-nya sendiri
5. Semua metode punya return type dan type hint parameter (`CLAUDE.md`)
6. Sudah di-review minimal satu anggota tim lain
7. Endpoint baru sudah dicatat untuk tim frontend

---

## Saran Pembagian Kerja

Setelah Fase 0 dan 1 selesai, tiga jalur ini bisa jalan paralel:

| Jalur | Tugas | Catatan |
|---|---|---|
| **A — Mesin ukuran** | Fase 2, 3 | Beri ke orang terkuat. Ini pembeda produknya, dan paling banyak logikanya |
| **B — Auth & seller** | Fase 4, 5 | Paling banyak CRUD, cocok untuk anggota yang sedang belajar |
| **C — Katalog & belanja** | Fase 6 | Perlu menunggu `BE-052` dari jalur B |

Fase 7 sebaiknya dikerjakan bersama setelah ketiga jalur bertemu — ini bagian yang menyentuh uang dan stok, risiko bug-nya paling mahal.

**Saran urutan demo:** Fase 0 → 1 → 2 → 3 → 6 (sebagian) sudah cukup untuk mendemokan fitur inti FitMate tanpa perlu checkout jalan. Kalau ada tenggat presentasi, kejar sampai `BE-062`.
