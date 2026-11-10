---
paths:
  - 'resources/views/layouts/**'
---

# Layouts

## Back-office admin memakai layouts/admin + AdminController untuk transaksi
`layouts/admin.blade.php` = master layout back-office (tanpa navbar/footer toko). Semua `pages::admin-*` dan `superadmin-settings` wajib `#[Layout('layouts::admin')]`; view controller memakai `<x-layouts::admin title="...">`. Transaksi admin = `AdminController@transactions` (GET /admin/transactions, view `admin/transactions`) dan `@updateStatus` (POST /admin/transactions/{order}/status). Tombol status hanya lewat `Order::adminActions()` / `Order::applyAdminStatus()` yang memanggil `Payment::settle` dan `StoreOrder::process/ship/complete/cancel` — jangan update `orders.status` langsung. Nota pembeli `pages::berhasil` (/berhasil/{order}, route `orders.invoice`) memakai `wire:poll` + `x-ui.order-status` supaya warna status ikut berubah.
