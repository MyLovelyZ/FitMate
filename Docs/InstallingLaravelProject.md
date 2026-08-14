# Cara Setup FitMate Setelah Clone dari GitHub

Panduan singkat menjalankan project ini di komputer baru.

---

## 1. Yang Harus Sudah Terpasang

| Kebutuhan | Versi | Catatan |
| --- | --- | --- |
| PHP | 8.3 atau lebih baru | project dikembangkan di PHP 8.4 |
| Composer | 2.x | |
| Node.js + npm | Node 20+ | dipakai Vite 8 & Tailwind 4 |
| MySQL / MariaDB | 8.x / 10.x | paling gampang lewat **Laragon** atau **XAMPP** |
| Git | &mdash; | |

Cek cepat semuanya sudah ada:

```bash
php -v
composer -V
node -v
npm -v
```

Ekstensi PHP yang wajib aktif di `php.ini` (biasanya sudah default di Laragon/XAMPP):
`pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`.

---

## 2. Clone Project

```bash
git clone <url-repo-fitmate>
cd FitMate
```

---

## 3. Buat Database Kosong

Nyalakan MySQL dulu (Start di panel Laragon/XAMPP), lalu buat database bernama **`FitMate`**:

```bash
mysql -u root -p -e "CREATE DATABASE FitMate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Atau lewat phpMyAdmin: **New** &rarr; nama database `FitMate` &rarr; **Create**.

> Langkah ini harus selesai **sebelum** langkah 4, karena setup otomatis langsung menjalankan migration.

---

## 4. Setup Otomatis (Cara Cepat)

Project ini punya script siap pakai di `composer.json`:

```bash
composer setup
```

Satu perintah itu menjalankan:

1. `composer install` &mdash; install dependency PHP
2. menyalin `.env.example` &rarr; `.env` (kalau `.env` belum ada)
3. `php artisan key:generate` &mdash; bikin APP_KEY
4. `php artisan migrate --force` &mdash; bikin semua tabel
5. `npm install` &mdash; install dependency JS
6. `npm run build` &mdash; build asset frontend

Kalau kredensial MySQL di komputermu bukan `root` tanpa password, **hentikan di sini kalau gagal**, edit `.env` dulu (lihat langkah 5), baru jalankan `php artisan migrate`.

---

## 4b. Setup Manual (Kalau Ingin Per Langkah)

```bash
composer install
copy .env.example .env      # Windows CMD/PowerShell
# cp .env.example .env      # Git Bash / Linux / macOS

php artisan key:generate
php artisan migrate
npm install
npm run build
```

---

## 5. Sesuaikan File `.env`

Buka `.env` dan pastikan bagian database cocok dengan MySQL lokalmu:

```env
APP_NAME=FitMate
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=FitMate
DB_USERNAME=root
DB_PASSWORD=
```

Isi `DB_PASSWORD` kalau MySQL-mu pakai password (XAMPP biasanya kosong, Laragon juga kosong).

Setelah mengubah `.env`, bersihkan cache config:

```bash
php artisan config:clear
```

---

## 6. Isi Data Awal (Opsional)

```bash
php artisan db:seed
```

Saat ini seeder baru membuat satu user contoh: `test@example.com`.

Kalau mau reset database dari nol sekaligus seed ulang:

```bash
php artisan migrate:fresh --seed
```

> `migrate:fresh` **menghapus semua tabel beserta isinya**. Jangan dipakai di server production.

---

## 7. Jalankan Aplikasi

**Cara paling praktis** &mdash; satu perintah menjalankan web server + queue worker + Vite sekaligus:

```bash
composer run dev
```

Buka http://localhost:8000

**Atau jalankan terpisah di dua terminal:**

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

---

## 8. Verifikasi Semuanya Jalan

```bash
php artisan about          # ringkasan environment & koneksi database
php artisan migrate:status # semua migration harus berstatus "Ran"
php artisan route:list     # daftar route yang terdaftar
php artisan test --compact # jalankan test suite (Pest)
```

---

## Perintah Harian Saat Ngoding

| Perintah | Kegunaan |
| --- | --- |
| `composer run dev` | jalankan server + queue + Vite sekaligus |
| `php artisan migrate` | jalankan migration baru dari teman satu tim |
| `php artisan migrate:fresh --seed` | reset database dari nol |
| `php artisan tinker` | coba kode PHP langsung di konteks aplikasi |
| `php artisan pail` | lihat log secara realtime |
| `vendor/bin/pint --dirty` | rapikan format kode PHP sebelum commit |
| `php artisan test --compact` | jalankan semua test |

Setelah `git pull` yang membawa perubahan dependency atau database:

```bash
composer install
npm install
php artisan migrate
```

---

## Masalah yang Sering Muncul

**`SQLSTATE[HY000] [1049] Unknown database 'FitMate'`**
Database belum dibuat. Ulangi langkah 3.

**`SQLSTATE[HY000] [2002] No connection could be made`**
MySQL belum nyala. Start dulu lewat panel Laragon/XAMPP.

**`SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'`**
Username/password di `.env` salah. Perbaiki lalu `php artisan config:clear`.

**`No application encryption key has been specified`**
Jalankan `php artisan key:generate`.

**`Vite manifest not found` / tampilan berantakan tanpa CSS**
Asset belum di-build. Jalankan `npm run build` (untuk sekali jalan) atau `npm run dev` (mode development).

**Perubahan `.env` tidak terasa efeknya**
Jalankan `php artisan config:clear`, dan kalau perlu `php artisan cache:clear`.

**Halaman error 500 tanpa pesan jelas**
Cek `storage/logs/laravel.log`, atau jalankan `php artisan pail` sambil membuka halamannya.

**Error permission di folder `storage` (Linux/macOS)**

```bash
chmod -R 775 storage bootstrap/cache
```

---

## Struktur Dokumentasi Lain

| File | Isi |
| --- | --- |
| `Docs/migrationDocs.md` | penjelasan tiap migration |
| `Docs/viewsstructure.md` | struktur folder view |
| `Docs/todo.md` | daftar pekerjaan |
| `tabeldatabase/index.html` | visualisasi skema database (buka di browser) |
