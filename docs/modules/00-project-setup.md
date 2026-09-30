# Module 00 — Project Setup & Scaffolding

- **Status:** ✅ Complete
- **Phase:** Phase 1 — Foundation
- **Completed on:** 2026-09-22
- **Owner agent:** `module-builder`

> Bootstraps the Laravel 13 API and the Next.js/TypeScript frontend with the base packages, configuration, and tooling every later module builds on.

## Scope

Includes: create `backend/` (Laravel 13); install and configure `php-open-source-saver/jwt-auth` (or `tymon/jwt-auth`) and `spatie/laravel-permission`, and set cache + queue to the `database` driver (no Redis for now — `file` is an acceptable cache alternative). Create `frontend/` (Next.js latest, TypeScript, App Router) with the folder structure from spec §48 (`app/`, `components/`, `features/`, `hooks/`, `lib/`, `services/`, `types/`, `utils/`). Base configs: `.env` files, CORS allowing the frontend origin, a base API route group under `/api` (no version prefix), Laravel Pint, ESLint/Prettier, git repository init, OpenAPI/Swagger scaffolding. A health endpoint.

Excludes: any domain tables, auth logic, or business features — those belong to later modules. No DB tables of its own except publishing the Spatie migration.

## Dependencies

None. This is the first module in the build order (spec §67).

## Database (tables owned / referenced)

- No domain tables. Publishes (does not seed) the Spatie migration for `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` — owned and seeded by Module 02.

## Permissions

None introduced. Permission scaffolding (Spatie config) is installed here; permission definitions land in Module 02.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /health | public | returns `{ status, service, time }` with 200 |

## Backend tasks

- [x] `composer create-project` Laravel 13 into `backend/`; configure `.env` (MySQL; `CACHE_STORE=database`; `QUEUE_CONNECTION=database`) — Laravel 13.32
- [x] Cache + queue tables — ship in Laravel 13's default migrations (`cache`, `jobs`, `job_batches`, `failed_jobs`); created via `php artisan migrate` (no Redis, no extra commands needed)
- [x] Install + configure jwt-auth (config published to `config/jwt.php`, `JWT_SECRET` generated via `jwt:secret`)
- [x] Install + configure spatie/laravel-permission; config + migration published and migrated
- [x] Configure CORS (`config/cors.php`) to allow the frontend origin only (`FRONTEND_URL`, default `http://localhost:3000`)
- [x] Register `/api` route group prefix (`bootstrap/app.php` → `withRouting(api: ...)`); wire `routes/api.php`
- [x] Add `GET /api/health` health-check route (`HealthController`)
- [x] Install Pint (ships with Laravel); add OpenAPI via Scramble (`dedoc/scramble`) — UI at `/docs/api`, JSON at `/docs/api.json`
- [x] Structure `app/` per spec §49 (`Actions/`, `Services/`, `Enums/`, `Policies/`, `Http/Requests/`, `Http/Resources/`)

## Frontend tasks

- [x] `create-next-app` (TypeScript, App Router, Tailwind 4) into `frontend/` — Next.js 16, React 19
- [x] Create folder structure per spec §48 (role areas: `admin/`, `teacher/`, `parent/`, `student/`, `(public)/`, `(auth)/`; plus `components/ features/ hooks/ lib/ services/ types/ utils/`)
- [x] Configure ESLint + Prettier (`eslint-config-prettier`, `.prettierrc.json`, `format` scripts); set `NEXT_PUBLIC_API_BASE_URL` env
- [x] Base typed API client stub in `services/` (`apiClient.ts`, points at `/api`, JWT-header-ready)

## Acceptance criteria

- [x] Backend boots via `php artisan serve`; frontend boots/builds via `npm run dev` / `npm run build`
- [x] `GET /api/health` returns HTTP 200 (verified over HTTP and via feature test)
- [x] JWT and Spatie packages installed and configured (secret set, config published, migration run)
- [x] CORS allows the frontend origin (not wildcard)
- [x] Tests written and passing (`tests/Feature/HealthCheckTest.php` — 1 test, 5 assertions)
- [x] Security review passed (JWT secret only in `.env` which is git-ignored; `.env.example` has an empty `JWT_SECRET`; CORS restricted to `FRONTEND_URL`, not `*`)

## Notes

Frontend and backend live in sibling folders under the repo root. Deployment targets (spec §2): `api.` / `app.` / root domain. Cache and queue run on the `database` driver for now (`file` is fine for cache); Redis is deferred — the spec still lists it as an optional future upgrade, and the driver config is env-only, so switching to it later needs no code change.

**Build notes (2026-09-22):**
- Laravel 13 requires PHP 8.3+. The machine's default `php` is 7.4 (XAMPP); Laragon's **PHP 8.3.30** is used for all backend commands. Documented in [backend/CLAUDE.md](../../backend/CLAUDE.md).
- Modern Laravel (11+) has no `Http/Kernel`/`config/cors.php` by default — routing is configured in `bootstrap/app.php`, and `config/cors.php` was published via `config:publish cors`.
- OpenAPI uses **Scramble** (annotation-free) instead of l5-swagger.
- Root git repo initialized on `main`; the initial commit is left for the user to make (commit-when-asked). `backend/` and `frontend/` nested git repos removed so the monorepo owns history.
