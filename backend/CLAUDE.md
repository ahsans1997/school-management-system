# Backend — Laravel 13 API

Guidance for the School Management System API. The root [../CLAUDE.md](../CLAUDE.md) holds the product-wide architecture rules; this file covers backend specifics. See the module plan in [../docs/](../docs/).

## ⚠️ PHP version (important on this machine)

The system-default `php` in PATH is **7.4 (XAMPP)** and **cannot run Laravel 13** (needs PHP 8.3+). Use Laragon's PHP 8.3 explicitly. In a shell:

```bash
export PATH="/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:$PATH"
php -v   # must show 8.3.x before running artisan/composer
```

Composer uses whatever `php` is first on PATH, so set this before `composer` too. (Alternatively configure Laragon to put PHP 8.3 on PATH globally.)

## Stack & conventions

- Laravel 13, REST API under **`/api`** (no version segment). Routes in `routes/api.php`, registered in `bootstrap/app.php` via `withRouting(api: ...)`.
- **JWT auth** via `php-open-source-saver/jwt-auth` (config `config/jwt.php`, secret in `.env`). Guard wiring + `JWTSubject` on the User model land in Module 01.
- **Spatie Laravel Permission** (config `config/permission.php`, tables migrated). Permissions/roles seeded in Module 02.
- **Cache + queue on the `database` driver** (`CACHE_STORE=database`, `QUEUE_CONNECTION=database`) — no Redis. The `cache`, `jobs`, `job_batches`, `failed_jobs` tables ship in Laravel 13's default migrations.
- **CORS** locked to `FRONTEND_URL` (`config/cors.php`), default `http://localhost:3000`.
- **OpenAPI** via Scramble — interactive docs at `/docs/api`, JSON at `/docs/api.json` (zero-annotation, generated from routes/types).
- Layering (enforced): Controller → Form Request → Action/Service → Eloquent → Model; responses via API Resources. `app/` has `Actions/`, `Services/`, `Enums/`, `Policies/`, `Http/Requests/`, `Http/Resources/`.

## Commands (prefix PATH with PHP 8.3 first)

```bash
php artisan serve                 # http://localhost:8000
php artisan migrate               # MySQL db: school_management
php artisan test                  # PHPUnit 12
php artisan test --filter=Name    # single test
php artisan queue:work            # process the database queue
./vendor/bin/pint                 # format
```

## Database

MySQL/MariaDB on `127.0.0.1:3306`, database `school_management` (utf8mb4), user `root` (no password in local dev). Ensure the MySQL service (Laragon or XAMPP) is running before `migrate`.

## Health check

`GET /api/health` → `200 {"status":"ok","service":...,"time":...}`. Feature test: `tests/Feature/HealthCheckTest.php`.
