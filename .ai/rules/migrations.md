---
paths:
  - 'database/migrations/**'
---

# Migrations

## Jalur uang: escrow, buku besar, dan idempotensi
FitMate menahan uang pembeli sebagai pihak ketiga. Empat aturan yang sudah dikunci di skema dan tidak boleh dilanggar kode:

1. `wallets.balance_available` / `balance_pending` adalah CACHE. Sumber kebenaran = `wallet_transactions` (available) dan `escrow_holds` yang masih `held` (pending). Ubah saldo hanya di dalam `DB::transaction` + `lockForUpdate()`, dan selalu barengan dengan satu baris ledger.

2. Setiap tulisan uang wajib punya kunci idempotensi. `wallet_transactions.idempotency_key` unique (NOT NULL) dan `payment_events` unique `(provider, event_id)`. Gateway mengirim webhook berkali-kali — tanpa dua unique ini satu pembayaran bisa mengisi wallet seller berulang.

3. Escrow ditahan per `store_order`, bukan per `order`. Pembeli bayar sekali, tapi tiap toko mengirim sendiri dan diterima di waktu berbeda. `escrow_holds.store_order_id` unique.

4. `order_items` dan `payouts` menyimpan salinan (nama produk, ukuran, warna, harga, nomor rekening), `orders` menyimpan salinan alamat. Jangan ganti jadi FK — nota lama harus tetap menunjukkan keadaan saat transaksi.

`escrow_holds.auto_release_at` diisi saat kurir menandai terkirim; scheduler mencairkan setelah lewat batas itu KECUALI ada `disputes` yang masih `open`/`under_review`.
