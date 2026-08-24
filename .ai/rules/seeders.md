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
