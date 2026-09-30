# Module 04 — School Settings

- **Status:** ✅ Complete
- **Phase:** Phase 1 — Foundation
- **Completed on:** 2026-09-30

> Singleton school/institution configuration — identity, system, and academic-pointer settings — read on nearly every request and served from cache with invalidation on change.

## Scope

Includes (spec §8): school information (name, logo, favicon, address, phone, email, website, EIIN, school code, established year, principal, socials); system settings (currency, timezone, date format, language, email/SMS/payment settings); pointer to the active academic year. Singleton-style access (GET/PUT), logo/favicon upload with validation, and cache read-through with invalidation on update.

Excludes: academic year/class/section/subject entities themselves (later academic modules — this stores only the active-year pointer), website/CMS settings (Website CMS phase).

## Dependencies

`03 — Users`.

## Database (tables owned / referenced)

- `school_settings` — singleton row: identity fields, `socials` (JSON), system fields with DB defaults (`BDT`, `Asia/Dhaka`, `d/m/Y`, `en`, name `My School`), and three **encrypted** secret groups `email_settings`, `sms_settings`, `payment_settings` (`text` columns, `encrypted:array` casts), plus `active_academic_year_id` (nullable, indexed; the FK is added once `academic_years` exists — Module 06).

## Permissions

`settings.view` (read) and `settings.manage` (write/upload), seeded in Module 02 (Admin has both; Principal has `settings.view`).

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /settings | settings.view | the singleton, served from cache |
| PUT | /settings | settings.manage | partial update; busts the cache |
| POST | /settings/logo | settings.manage | multipart; png/jpg/jpeg/webp, extension + real MIME, ≤ 2 MB |
| POST | /settings/favicon | settings.manage | multipart; png/ico, extension + real MIME, ≤ 512 KB |

## Backend tasks

- [x] `school_settings` migration + `SchoolSetting` model with `current()` singleton accessor
- [x] Form Requests for update + uploads (validation incl. `timezone` rule, MIME + max size)
- [x] Actions: `UpdateSchoolSettingsAction` (merges secret groups), `StoreSettingImageAction` (random filename, deletes the replaced file)
- [x] `SchoolSettingsResource` — secrets never returned; each becomes a `<key>_set` boolean
- [x] Cache read-through (spec §60) keyed `school.settings`; busted on every write
- [x] Routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [x] `admin/settings` form — Branding, School information, Social links, System, Email (SMTP), SMS, Payment gateway
- [x] Logo + favicon upload with client-side type/size hints, preview, and error states
- [x] Typed `settingsService`; `apiClient` now sends `FormData` as multipart
- [x] Page needs `settings.view`; editing/saving/upload only with `settings.manage` (read-only otherwise)

## Acceptance criteria

- [x] Settings persist and are served from cache (test proves a direct DB write stays invisible until the API busts the cache)
- [x] Logo/favicon upload is validated (MIME + size); invalid files rejected (live: a PHP web-shell payload → 422)
- [x] Updating settings invalidates the cache (next read reflects the change)
- [x] No secret leakage in the response (secrets encrypted at rest; response carries only `*_set` flags)
- [x] Tests written and passing (13 settings tests, 39 assertions; 50 backend tests total)
- [x] Security review passed (upload validation, secrets encrypted + masked, `settings.manage` gated, SVG excluded)

## Notes

Single-school platform, so one settings row is the source of truth (spec §1). This stores config, not amounts.

**Build notes (2026-09-30):**
- **Never cache Eloquent models/objects.** Laravel 13 ships `cache.serializable_classes = false`, so any object read back from a serializing store (database/file/redis) comes back as `__PHP_Incomplete_Class`. `current()` therefore caches the raw attribute array and rehydrates with `newFromBuilder()`. Tests use the `array` store (no serialization), which hid this — found only in the live check; a regression test now forces the `database` store.
- A lazy `firstOrCreate([])` inside a read caused two bugs: the model lacked DB-level column defaults (nulls got cached) and `wasRecentlyCreated` made a GET return **201**. Fixed by `refresh()` after a lazy create + rehydrating from attributes.
- Secret groups merge over stored values; blank/null keys are ignored, so the UI never has to resend a secret (the password field starts empty with a "set — leave blank to keep" hint).
- SVG deliberately excluded from uploads (can embed script → XSS when served from the app origin). Filenames are always server-generated.
- `storage:link` created (`public/storage` → `storage/app/public`). Stored images load fine via `<img>`; a JS `fetch()` of `/storage/*` from the frontend origin is CORS-blocked, since CORS only covers `api/*` — irrelevant for display.
- Verified end-to-end in the browser: the real form saved name/year/SMTP host+password (response shows `password_set: true`, never the value); a canvas-generated PNG uploaded through the real file input was stored and served (120×60 preview rendered); `.txt` refused client-side.
- Dev DB now holds sample settings: "Greenfield High School", established 1995, SMTP host `smtp.greenfield.test`, and a logo.
