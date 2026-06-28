# SMARTIQ Backend API

Backend SMARTIQ dibangun dengan PHP 8.1+, Laravel Lumen 10, MySQL, Eloquent, dan PHPUnit. API menyediakan autentikasi Bearer Token, pengelolaan kuis live, assignment, peserta, jawaban, laporan, dan leaderboard.

## Persyaratan

- PHP 8.1 atau lebih baru
- Ekstensi PHP: `ctype`, `dom`, `filter`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, dan `xmlwriter`
- MySQL 8.0+ atau MariaDB
- Composer 2.x

Periksa versi dan kebutuhan platform:

```bash
php --version
composer --version
composer check-platform-reqs
```

## Instalasi lokal

### 1. Clone repository dan gunakan branch backend

```bash
git clone https://github.com/selviaalovelin/smartiq-backend.git
cd smartiq-backend
git checkout backend
```

### 2. Install dependency

```bash
composer install
```

Jangan gunakan `--ignore-platform-reqs`. Jika instalasi gagal, lengkapi ekstensi PHP yang disebutkan Composer.

### 3. Buat file environment

Linux/macOS/Git Bash:

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Sesuaikan `.env`:

```ini
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartiq
DB_USERNAME=root
DB_PASSWORD=

FRONTEND_URL=http://localhost:5173
```

Jangan commit file `.env` karena dapat berisi kredensial lokal.

### 4. Buat database dan jalankan migrasi

Buat database aplikasi:

```sql
CREATE DATABASE smartiq CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Jalankan migrasi:

```bash
php artisan migrate
```

Gunakan `migrate:fresh` hanya pada database lokal/testing yang aman untuk dihapus.

### 5. Jalankan backend

```bash
php -S localhost:8000 -t public
```

Verifikasi melalui `http://localhost:8000`. Response yang diharapkan:

```json
{
  "name": "SMARTIQ Backend",
  "framework": "Lumen (10.0.4) (Laravel Components ^10.0)"
}
```

Frontend Vite harus mem-proxy `/api` ke `http://localhost:8000`.

## Pengujian

Test tidak boleh menggunakan database aplikasi. Buat database khusus:

```sql
CREATE DATABASE smartiq_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`phpunit.xml` mengatur `DB_DATABASE=smartiq_testing`. Siapkan ulang schema testing dan jalankan seluruh test:

Windows PowerShell:

```powershell
$env:DB_DATABASE='smartiq_testing'
php artisan migrate:fresh
php vendor/bin/phpunit
```

Linux/macOS/Git Bash:

```bash
DB_DATABASE=smartiq_testing php artisan migrate:fresh
php vendor/bin/phpunit
```

## Autentikasi

Endpoint pengajar menggunakan Bearer Token:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Pengajar hanya dapat membaca atau mengubah quiz dan assignment miliknya sendiri. Akses milik pengguna lain menghasilkan JSON `403`.

## Endpoint API

### Auth

| Method | Endpoint | Auth |
| --- | --- | --- |
| POST | `/api/auth/register` | Tidak |
| POST | `/api/auth/login` | Tidak |
| POST | `/api/auth/logout` | Ya |
| POST | `/api/auth/forgot-password` | Tidak |
| POST | `/api/auth/reset-password` | Tidak |

### Quiz

| Method | Endpoint | Auth |
| --- | --- | --- |
| GET | `/api/quizzes` | Ya |
| POST | `/api/quizzes` | Ya |
| GET | `/api/quizzes/{id}` | Ya |
| PUT | `/api/quizzes/{id}` | Ya |
| DELETE | `/api/quizzes/{id}` | Ya |
| GET | `/api/quizzes/pin/{pin}` | Tidak |
| PUT | `/api/quizzes/{id}/open` | Ya |
| PUT | `/api/quizzes/{id}/start` | Ya |
| PUT | `/api/quizzes/{id}/finish` | Ya |
| GET | `/api/quizzes/{id}/participants` | Ya |
| POST | `/api/quizzes/{id}/participants` | Tidak |
| POST | `/api/quizzes/{id}/participants/{participantId}/answers` | Tidak |
| GET | `/api/quizzes/{id}/leaderboard` | Tidak |
| DELETE | `/api/quizzes/{id}/live-report` | Ya |

Untuk membuka assignment dari PIN, gunakan query `assignment_id`:

```text
/api/quizzes/pin/{pin}?assignment_id={assignmentId}
```

### Assignment

| Method | Endpoint | Auth |
| --- | --- | --- |
| GET | `/api/assignments` | Ya |
| POST | `/api/assignments` | Ya |
| GET | `/api/assignments/{id}/participants` | Ya |
| DELETE | `/api/assignments/{id}` | Ya |

## Format response JSON

Response sukses:

```json
{
  "message": "Operasi berhasil.",
  "data": {}
}
```

Response error API selalu JSON:

| Status | Bentuk response |
| --- | --- |
| 401 | `{ "message": "Silakan masuk terlebih dahulu." }` |
| 403 | `{ "message": "Anda tidak memiliki akses." }` |
| 404 | `{ "message": "Data tidak ditemukan." }` |
| 422 | `{ "message": "Data yang diberikan tidak valid.", "errors": {} }` |

## File yang tidak boleh di-commit

- `.env`
- `vendor/`
- `.phpunit.result.cache`
- `storage/logs/`
- `.codegraph/`
