---
paths:
  - 'database/**'
---

# Database

## size_guides.measurement_key harus sama dengan nama kolom di user_body_profiles
Rekomendasi ukuran bekerja dengan mencocokkan kolom user_body_profiles ke rentang di size_guides. Karena itu measurement_key WAJIB memakai nama kolom persis: chest, waist, hip, foot_length. Kalau namanya beda, pencocokan butuh tabel pemetaan manual dan itu yang mau dihindari.

Pengecualian: 'length' (panjang baju) ada di size_guides tapi ngk ada di user_body_profiles, karena itu ukuran produknya, bukan ukuran badan. Cuma dipakai buat ditampilkan di tabel ukuran.

Nambah dimensi baru = tambah kolom di user_body_profiles + baris di SizeGuideSeeder::MEASUREMENTS dengan key yang sama.
