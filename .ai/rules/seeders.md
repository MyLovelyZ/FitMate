---
paths:
  - 'database/seeders/**'
---

# Seeders

## Seeder data referensi wajib idempotent dan urut
CategoryType, Category, Size, SizeGuide adalah data referensi (standar resmi FitMate), bukan data dummy. Selalu pakai updateOrCreate dengan kunci alami (slug, atau kombinasi category_type_id+size_type+name), jangan create() polos, biar seeder aman dijalankan ulang tanpa migrate:fresh.

Urutan di DatabaseSeeder ngk boleh diacak: CategoryType -> Category -> Size -> SizeGuide -> User. Seeder di bawah nyari induknya pakai firstOrFail(), jadi bakal error kalau urutannya kebalik.

Rentang angka di UserBodyProfileFactory sengaja dipaskan ke tabel di SizeGuideSeeder. Kalau tabel ukurannya diubah, sesuaikan juga factory-nya, kalau ngk profil hasil seeding ngk dapat rekomendasi ukuran.

## Urutan DatabaseSeeder: warna dan produk masuk sebelum User
Urutan sekarang: CategoryType -> Category -> Size -> SizeGuide -> Color -> Product -> User. ColorSeeder wajib sebelum ProductSeeder (ProductSeeder melempar RuntimeException kalau tabel colors kosong), dan CategorySeeder + SizeSeeder wajib sebelum ProductSeeder karena dipanggil pakai firstOrFail().

ColorSeeder = data referensi (palet resmi), idempotent lewat updateOrCreate by slug. ProductSeeder = katalog demo, idempotent lewat updateOrCreate: produk by slug, varian by (product_id, size_id, color_id).

ProductSeeder sengaja mengambil ukuran dari `category_type_id` kategori produknya sendiri, bukan ukuran acak, supaya fitur rekomendasi ukuran bisa langsung dicoba dari data hasil seeding. Kategori dengan `has_sizes = false` (aksesoris) dapat satu varian tanpa size_id.

## UserSeeder dan StoreSeeder jalan sebelum ProductSeeder
Sejak produk jadi milik toko (`products.store_id` NOT NULL), urutan DatabaseSeeder berubah jadi: CategoryType -> Category -> Size -> SizeGuide -> Color -> User -> Store -> Product.

UserSeeder bikin tiga akun seller (seller@, seller2@, seller3@fitmate.test). StoreSeeder mencari akun itu pakai `firstOrFail()` lalu bikin satu toko per seller. ProductSeeder membagi 30 produk demo bergiliran ke toko yang `status = active`, dan melempar RuntimeException kalau belum ada toko.

Kalau nambah seller demo baru, tambahkan juga barisnya di `StoreSeeder::STORES` — kalau ngk, seller itu ngk punya toko dan ngk bisa berjualan.

## Seeder data contoh: idempotent lewat pencarian, bukan updateOrCreate
Data acuan (CategoryType, Category, Size, SizeGuide, Color, Store) pakai `updateOrCreate` dengan kunci alami. Data contoh yang bentuknya bervariasi tiap baris tidak bisa begitu, jadi polanya beda:

- `UserSeeder` mencari akun lewat email dulu (`firstOrCreateUser`), dan cuma menambah pembeli acak sebanyak kekurangannya sampai `BUYER_COUNT`.
- `MarketplaceOrderSeeder` berhenti di awal kalau `Order` sudah ada. Mau data baru: `migrate:fresh --seed`.

Keduanya wajib tetap aman dijalankan dua kali — `php artisan db:seed` sempat error `UNIQUE constraint failed: users.email` gara-gara ini.

Urutan lengkap sekarang: CategoryType -> Category -> Size -> SizeGuide -> Color -> User -> Store -> Product -> Wallet -> MarketplaceOrder.
