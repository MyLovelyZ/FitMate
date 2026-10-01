---
paths:
  - 'resources/views/pages/**'
---

# Pages

## Filter yang disimpan di URL harus dirender ulang ke elemennya
`wire:model` tidak menulis `value`/`selected` ke HTML yang dikirim server. Jadi halaman yang menyimpan state di query string lewat `#[Url]` (mis. filter katalog di `⚡catalog`) harus menulis nilainya sendiri: `value="{{ $minPrice }}"` pada input dan `@selected($sort === $value)` pada `<option>`.

Tanpa itu, URL yang dibagikan memang menghasilkan daftar produk yang benar, tapi kotak filternya tampil kosong/salah sampai Livewire selesai dimuat.
