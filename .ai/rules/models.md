---
paths:
  - app/Models/ProductImage.php
  - app/Models/Order.php
  - app/Models/Cart.php
---

# Models

## product_images.path relatif ke public/, tampilkan lewat ->url
`path` disimpan relatif ke folder `public/` dan dirender dengan accessor `$image->url` (= `asset($path)`), sama seperti gambar statis di home. Unggahan seller nanti (BE-053) yang masuk disk `public` harus disimpan dengan awalan `storage/` (mis. `storage/products/x.webp`), jangan path disk mentah.

Satu `is_primary` per produk dijaga model event: foto pertama otomatis jadi utama, menandai foto lain sebagai utama melepas yang lama. Pakai `Product::images()` (utama di depan) untuk galeri dan `Product::primaryImage()` untuk daftar/thumbnail.

## Status pesanan untuk pembeli = Order::BUYER_STATUS_GROUPS
Pembeli tidak melihat nilai `orders.status` mentah. Tab "Pesanan Saya", lencana `x-ui.order-status`, dan progres `x-ui.order-steps` semuanya membaca `Order::BUYER_STATUS_GROUPS` (belum-dibayar, diproses = paid+processing, dikirim, selesai, dibatalkan = cancelled+expired+refunded) lewat `buyerStatusGroup()` / `buyerStepIndex()` / scope `inBuyerStatusGroup()`. Status paket per toko pakai `StoreOrder::BUYER_STATUS_LABELS`, status kurir `Shipment::STATUS_LABELS`. Kalau menambah status baru di enum migrasi, masukkan juga ke salah satu grup.

## Tambah ke keranjang lewat Cart::add, lalu dispatch `cart-updated`
Jangan menulis `cart_items` langsung. Pakai `Cart::forUser($user)->add($variant, $qty)`: jumlahnya dibatasi stok varian (termasuk yang sudah di keranjang) dan mengembalikan jumlah yang benar-benar ditambahkan (0 = stok habis). Setiap komponen yang mengubah isi keranjang wajib `$this->dispatch('cart-updated', count: $user->cartQuantity())` supaya lencana keranjang di navbar ikut berubah. Keranjang hanya untuk role `user`; tamu diarahkan ke login dengan `redirect()->setIntendedUrl(...)` karena URL request Livewire bukan URL halaman.
