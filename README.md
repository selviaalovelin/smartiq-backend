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

Endpoint yang sudah dibuat:

- `GET /api/quizzes`
- `POST /api/quizzes`
- `GET /api/quizzes/{id}`
- `PUT /api/quizzes/{id}`
- `DELETE /api/quizzes/{id}`

Untuk sementara data yang disimpan baru judul kuis, kategori, dan PIN.
