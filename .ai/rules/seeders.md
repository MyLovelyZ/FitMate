---
paths:
  - 'database/seeders/**'
---

# Seeders

## Seeder data referensi wajib idempotent dan urut
CategoryType, Category, Size, SizeGuide adalah data referensi (standar resmi FitMate), bukan data dummy. Selalu pakai updateOrCreate dengan kunci alami (slug, atau kombinasi category_type_id+size_type+name), jangan create() polos, biar seeder aman dijalankan ulang tanpa migrate:fresh.

Urutan di DatabaseSeeder ngk boleh diacak: CategoryType -> Category -> Size -> SizeGuide -> User. Seeder di bawah nyari induknya pakai firstOrFail(), jadi bakal error kalau urutannya kebalik.

Rentang angka di UserBodyProfileFactory sengaja dipaskan ke tabel di SizeGuideSeeder. Kalau tabel ukurannya diubah, sesuaikan juga factory-nya, kalau ngk profil hasil seeding ngk dapat rekomendasi ukuran.
