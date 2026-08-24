---
paths:
  - resources/views/partials/navbar.blade.php
---

# Partials

## Pembungkus Alpine navbar wajib `class="contents"`
Header, panel mobile, dan overlay dibungkus satu root Alpine agar berbagi state `open`. Root itu harus `class="contents"` — tanpa itu `sticky top-0` di header mati, karena sticky hanya berlaku di dalam kotak induknya yang tingginya cuma setinggi header.

Panel mobile dan overlay pakai `top-header` supaya sejajar dengan tinggi header (`--spacing-header`, 76px). Kalau tinggi header diubah, ubah token itu, jangan hardcode.
