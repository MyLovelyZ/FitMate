# FitMate

Aplikasi FitMate terdiri dari dua bagian:

- `BackendFitMate` — Laravel 13 (PHP 8.3+, database SQLite)
- `FrontendFitMate` — React 19 + TypeScript + Vite

Berikut cara setup setelah project baru di-clone dari GitHub.

---

## Persyaratan

| Kebutuhan | Versi minimum | Cara cek |
| --- | --- | --- |
| PHP | 8.3 | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | 20.19+ | `node -v` |

Pastikan ekstensi `pdo_sqlite` sudah aktif di `php.ini` (hapus tanda `;` di depan baris `extension=pdo_sqlite`). Cek dengan `php -m`.

---

## 1. Clone Repository

```powershell
git clone <url-repository>
cd FitMate
```

---

## 2. Setup Backend

```powershell
cd BackendFitMate
```

**a. Install dependency PHP**

```powershell
composer install
```

**b. Buat file `.env`**

```powershell
Copy-Item .env.example .env
```

> Git Bash / macOS / Linux: `cp .env.example .env`

**c. Generate application key**

```powershell
php artisan key:generate
```

**d. Buat file database mysql**

bebas sih mau di phpmyadmin atau dbngine

**e. Jalankan migrasi**

```powershell
php artisan migrate
php artisan db:seed
```

**f. Install dan build asset backend**

```powershell
npm install
npm run build
```

---

## 3. Setup Frontend

Kembali ke folder `FitMate`, lalu masuk ke folder frontend:

```powershell
cd ..\FrontendFitMate
npm install
```

---

## 4. Menjalankan Aplikasi

Buka **dua terminal** terpisah.

**Terminal 1 — Backend**

```powershell
cd BackendFitMate
php artisan serve
```

**Terminal 2 — Frontend**

```powershell
cd FrontendFitMate
npm run dev
```

| Layanan | Alamat |
| --- | --- |
| Backend | http://localhost:8000 |
| Frontend | http://localhost:5173 |

Hentikan server dengan `Ctrl + C` di masing-masing terminal.
