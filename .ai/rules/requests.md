---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Validasi integritas ukuran harus di after(), bukan rules()
Aturan BE-034 (`SizeChartMatchesCategory`, `EntryBelongsToSizeChart`) dijalankan dari hook `after()` di StoreProductRequest, bukan dari array `rules()`.

Alasannya: rule object yang dipasang bersama `nullable` akan dilewati begitu nilainya null. Kasus "kategori butuh ukuran tapi `size_chart_id` kosong" justru lolos validasi — persis lubang yang mau ditutup. Callback `after()` selalu jalan.

Jangan memindahkan aturan ini kembali ke `rules()`. Ada test-nya di tests/Feature/SizeIntegrityTest.php.
