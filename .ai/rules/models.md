---
paths:
  - app/Models/ProductImage.php
  - app/Models/Order.php
  - app/Models/Cart.php
  - app/Models/Store.php
  - app/Models/StoreOrder.php
  - app/Models/Payment.php
  - app/Models/Wallet.php
  - app/Models/EscrowHold.php
  - app/Models/Voucher.php
  - app/Models/User.php
---

# Models

## product_images.path relatif ke public/, tampilkan lewat ->url
`path` disimpan relatif ke folder `public/` dan dirender dengan accessor `$image->url` (= `asset($path)`), sama seperti gambar statis di home. Unggahan seller nanti (BE-053) yang masuk disk `public` harus disimpan dengan awalan `storage/` (mis. `storage/products/x.webp`), jangan path disk mentah.

Satu `is_primary` per produk dijaga model event: foto pertama otomatis jadi utama, menandai foto lain sebagai utama melepas yang lama. Pakai `Product::images()` (utama di depan) untuk galeri dan `Product::primaryImage()` untuk daftar/thumbnail.

## Status pesanan untuk pembeli = Order::BUYER_STATUS_GROUPS
Pembeli tidak melihat nilai `orders.status` mentah. Tab "Pesanan Saya", lencana `x-ui.order-status`, dan progres `x-ui.order-steps` semuanya membaca `Order::BUYER_STATUS_GROUPS` (belum-dibayar, diproses = paid+processing, dikirim, selesai, dibatalkan = cancelled+expired+refunded) lewat `buyerStatusGroup()` / `buyerStepIndex()` / scope `inBuyerStatusGroup()`. Status paket per toko pakai `StoreOrder::BUYER_STATUS_LABELS`, status kurir `Shipment::STATUS_LABELS`. Kalau menambah status baru di enum migrasi, masukkan juga ke salah satu grup.

## Tambah ke keranjang lewat Cart::add, lalu dispatch `cart-updated`
Jangan menulis `cart_items` langsung. Pakai `Cart::forUser($user)->add($variant, $qty)`: jumlahnya dibatasi stok varian (termasuk yang sudah di keranjang) dan mengembalikan jumlah yang benar-benar ditambahkan (0 = stok habis). Setiap komponen yang mengubah isi keranjang wajib `$this->dispatch('cart-updated', count: $user->cartQuantity())` supaya lencana keranjang di navbar ikut berubah. Keranjang untuk akun yang `canShop()` (role `user` dan `seller`), tapi seller tidak boleh membeli produk tokonya sendiri; tamu diarahkan ke login dengan `redirect()->setIntendedUrl(...)` karena URL request Livewire bukan URL halaman.

## Toko: satu pemilik, pengajuan diverifikasi admin, seller tetap bisa belanja
Marketplace multi-seller: banyak toko, tiap toko tepat 1 pemilik (`stores.user_id` unique), tanpa staf toko. KEP-5 diputuskan: pembeli mengajukan toko sendiri di `/buka-toko` (status `pending`), admin meninjau di `/admin/stores/{store}`. Ubah status hanya lewat `Store::approve/reject/suspend/reactivate` — `approve()` sekaligus mengubah role pemilik jadi `seller` dan memverifikasi rekening default. Seller tetap boleh belanja: cek fitur pembeli dengan `User::canShop()`, jangan `role === 'user'`.

Hanya toko `Store::canSell()` yang tampil: query katalog/halaman toko pakai scope `Product::purchasable()` / `Store::selling()`. NIK di `identity_number` ber-cast `encrypted` dan foto KTP di disk privat `Store::IDENTITY_DISK`; tampilkan KTP hanya lewat route `admin.stores.identity-card`, jangan pernah lewat `asset()`/disk `public`.

## Jalur transaksi lewat metode model, jangan update status/saldo langsung
Checkout = `Order::placeFromCart()` (kunci varian, salin data, kurangi stok, buat Payment). Bayar = `Payment::settle($eventId)` — dipakai tombol simulasi DAN webhook gateway nanti; idempotent lewat `payment_events`. Status paket hanya lewat `StoreOrder::process/ship/markDelivered/complete/cancel` (dijaga `TRANSITIONS`, lempar LogicException kalau tidak sah); status `orders` induk diturunkan `syncStatusFromStores()`, jangan diisi manual setelah dibayar. Saldo hanya lewat `Wallet::post()` (buku besar) / `adjustPending()` (cache escrow).

Komisi = `PLATFORM_FEE_RATE` × total paket (termasuk ongkir), sama dengan factory/seeder. Provider `simulator` + `Shipment::OPTIONS` flat adalah pengganti sementara KEP-3/KEP-4. Scheduler: `orders:expire-unpaid` (5 menit) dan `escrow:release-due` (per jam) di routes/console.php.

## Kode promo: diskon dibagi ke paket, kuota dihitung dari orders
Voucher (`percentage` dibatasi `max_discount`, atau `fixed` rupiah) hanya memotong subtotal barang, bukan ongkir, dibulatkan ke bawah ke rupiah. `Order::placeFromCart(voucherCode:)` mengunci baris voucher, cek ulang `Voucher::redeemProblem()`, lalu `Order::splitDiscount()` membagi diskon ke `store_orders.discount` sebanding subtotal paket (sisa pembulatan ke paket terakhir). Akibatnya `store_orders.total` = yang dibayar pembeli untuk paket itu: komisi, escrow `gross_amount`, dan refund semuanya dari angka setelah diskon. Jadi penjual yang menanggung diskon, belum ada subsidi platform.

Tidak ada kolom `used_count`: pemakaian = `Voucher::redemptions()` (orders dengan voucher_id yang statusnya bukan cancelled/expired), jadi order yang batal sebelum dibayar otomatis mengembalikan kuota. `orders.voucher_code` adalah salinan untuk nota. Cari kode lewat scope `withCode()` (kode selalu huruf besar).

## Profil toko baru diisi setelah pengajuan disetujui
`/buka-toko` (`pages::store-apply`) adalah halaman publik: tamu dan pembeli melihat landing page dulu, lalu "Mulai Daftar" membuka formulir 3 langkah (`STEPS`: alamat → KTP → rekening, divalidasi per langkah lewat `next()`). Pengajuan TIDAK berisi profil toko, jadi `stores.name`/`slug`/`description`/kontak masih NULL saat pending/rejected. Setelah `approve()`, pemilik mengisi profil lewat `saveProfile()` di halaman yang sama.

`canSell()` dan scope `selling()` juga mensyaratkan `hasProfile()`, jadi toko yang disetujui tapi belum berprofil (`needsProfile()`) belum tampil di katalog. Untuk menampilkan nama di admin/dashboard pakai `$store->displayName()` (fallback "Calon toko {nama KTP}"), jangan `$store->name`; `route('stores.show', $store)` hanya boleh dipanggil kalau `canSell()`.

## Blokir & hapus oleh superadmin: banned_at, bukan users.status
Akun diblokir lewat `User::ban($by, $reason)` / `unban()` (kolom `banned_at`, `banned_by`, `ban_reason`), BUKAN `users.status`. Middleware `EnsureUserIsNotBanned` (web group) mengeluarkan sesi yang diblokir dan login menolaknya. `ban()` ikut menangguhkan toko aktif; `unban()` sengaja tidak mengaktifkannya lagi (lewat review toko — superadmin boleh masuk area admin). Superadmin tidak bisa memblokir/menghapus sesama superadmin (`canModerate()`).

Hapus akun hanya lewat `deleteAccount()`, yang menolak kalau `deletionBlocker()` menemukan riwayat uang (pesanan, store_orders, wallet_transactions, payouts, refund, sengketa); akun seperti itu cukup diblokir. Produk diblokir lewat `Product::ban()` (terpisah dari `is_active` milik seller) dan dihapus dengan soft delete; `isPurchasable()`/scope `purchasable()` sudah mengecualikan `banned_at`.
