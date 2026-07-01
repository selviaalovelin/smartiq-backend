# SMARTIQ Backend

Backend untuk aplikasi kuis SMARTIQ. Project ini dibuat dengan Laravel Lumen dan dijalankan lewat Laragon.

## Setup lokal

Buat database MySQL:

```bash
smartiq
```

Install dependency:

```bash
composer install
```

Copy file env:

```bash
copy .env.example .env
```

Jalankan migration:

```bash
php artisan migrate
```

Jalankan server:

```bash
php -S localhost:8000 -t public
```

## API kuis

POST /api/auth/register
POST /api/auth/login
POST /api/auth/logout
GET /api/quizzes
POST /api/quizzes
PUT /api/quizzes/{id}
DELETE /api/quizzes/{id}
PUT /api/quizzes/{id}/open
PUT /api/quizzes/{id}/start
PUT /api/quizzes/{id}/finish
GET /api/quizzes/{id}/participants
GET /api/quizzes/{id}/leaderboard
GET /api/assignments
POST /api/assignments
