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

1. POST /api/auth/register
2. POST /api/auth/login
3. POST /api/auth/logout
4. GET /api/quizzes
5. POST /api/quizzes
6. PUT /api/quizzes/{id}
7. DELETE /api/quizzes/{id}
8. PUT /api/quizzes/{id}/open
9. PUT /api/quizzes/{id}/start
10. PUT /api/quizzes/{id}/finish
11. GET /api/quizzes/{id}/participants
12. GET /api/quizzes/{id}/leaderboard
13. GET /api/assignments
14. POST /api/assignments
