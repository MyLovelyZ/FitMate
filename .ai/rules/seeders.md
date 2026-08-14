---
paths:
  - database/seeders/SizeChartSeeder.php
---

# Seeders

## Angka standar ukuran masih provisional (KEP-1 belum diputuskan)
Rentang di SizeChartSeeder disusun mengacu ukuran tubuh dewasa Asia Tenggara supaya fitur bisa jalan dan didemokan. KEP-1 belum diputuskan tim — angkanya WAJIB ditinjau ulang (acuan SNI / data antropometri Indonesia) sebelum rilis produksi.

Dua aturan penyusunan yang harus dijaga saat menyunting:
- rentang antar entry bersambung (max entry sebelumnya = min entry berikutnya) supaya tidak ada orang yang jatuh di celah;
- `sort_order` naik dari ukuran terkecil ke terbesar.

SizeChartIntegrityChecker memeriksa celah dan tumpang tindih, hasilnya tampil di halaman admin standar ukuran.
