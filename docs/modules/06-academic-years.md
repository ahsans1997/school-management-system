# Module 06 — Academic Years

- **Status:** ✅ Complete
- **Phase:** Phase 2 — Academic Core
- **Completed on:** 2026-09-30

> Manages academic years/sessions (spec §13) — the top of the academic hierarchy that every class, enrollment, exam, and fee cycle is scoped to.

## Scope

Includes: CRUD for academic years and a dedicated "set current" action that flags exactly one year as active. Each year carries a display name, start/end dates, and an `is_current` flag. `AcademicYear::current()` is the cached accessor downstream modules default to.

Excludes: classes, sections, enrollments, or promotion logic (Modules 07, 08, 13). Term/semester breakdown within a year is deferred to V2.

## Dependencies

- `03 — Users` (authenticated actor and permission subject).

## Database (tables owned / referenced)

- `academic_years` — `id`, `name` (unique, ≤ 50), `start_date`, `end_date`, `is_current`, timestamps, plus a **stored generated column** `current_marker` = `1` when current / `NULL` otherwise with a **UNIQUE index**. Many NULLs pass but only one `1`, so the database itself allows at most one current year.
- Removed `school_settings.active_academic_year_id` (Module 04): it duplicated `is_current` and would have drifted. The current year now has one source of truth.
- **Rule for every future table that references a year:** `$table->foreignId('academic_year_id')->constrained()->restrictOnDelete();` — the delete guard depends on it.

## Permissions

`academic_year.view|create|edit|delete` (seeded in Module 02). Set-current is gated by `academic_year.edit`. Admin holds all; Principal holds `.view`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /academic-years | academic_year.view | paginated, newest first |
| POST | /academic-years | academic_year.create | first year ever becomes current |
| GET | /academic-years/{id} | academic_year.view | show |
| PUT | /academic-years/{id} | academic_year.edit | name/dates (not `is_current`) |
| DELETE | /academic-years/{id} | academic_year.delete | 409 if current or in use |
| POST | /academic-years/{id}/set-current | academic_year.edit | atomic switch |

## Backend tasks

- [x] Migration + `AcademicYear` model (unique `name`; DB-enforced single current year via `current_marker`)
- [x] Form Requests: `end_date` after `start_date`, unique name, **no overlapping date ranges**; `is_current` not accepted (set-current is the only path)
- [x] Policy: N/A — academic years are school-wide records with no ownership dimension, so `permission:` middleware is the authorization layer; invariants live in actions + DB constraints
- [x] `SetCurrentAcademicYearAction` in a transaction (lock → clear old → set new, in that order)
- [x] Delete guard: current year → 409; referenced year → FK `RESTRICT` error mapped to 409
- [x] API Resource + routes under `/api` with JWT + `permission:` middleware
- [x] `AcademicYear::current()` cached (attributes, not objects) and busted on create/update/switch/delete

## Frontend tasks

- [x] Admin page `admin/academic-years/` (paginated list, create form, inline edit) + nav entry
- [x] Typed `academicYearService`
- [x] "Set as current" with confirmation and a current-year badge; Delete/Set-current hidden for the current year
- [x] Validation + error states (date range hint client-side; duplicate name, overlap, 409 from the API)
- [x] Dashboard school card shows the current session

## Acceptance criteria

- [x] Exactly one year has `is_current = true` at all times; set-current is atomic (DB rejects a second current year even when the app is bypassed)
- [x] A year that is in use cannot be deleted (clear 409) — proven with an FK-restricted stand-in table until enrollments exist
- [x] List endpoint is paginated (`?page&per_page`)
- [x] Tests written and passing (14 academic-year tests, 58 assertions; 76 backend tests total)
- [x] Security review passed (permission gating, `is_current` not client-writable, no mass-assignment of the generated column)

## Notes

`is_current` drives default scoping across the app. Years may not overlap, so every date maps to at most one year.

**Build notes (2026-09-30):**
- The unique index on the generated column was probed directly on MariaDB 10.4: a second current row fails with `ERROR 1062 Duplicate entry '1'`. The same guarantee holds on SQLite in tests.
- The switch runs two statements (clear, then set) rather than `SET is_current = (id = ?)`: MySQL/MariaDB check uniqueness row by row, so a single multi-row update can fail depending on row order.
- Dev runs **MariaDB 10.4.27** (XAMPP) while the spec targets MySQL 8; 10.4 is end-of-life. Fine locally — production should run MySQL 8.
- Frontend: date-only values are formatted from local parts (`formatDay`), because `new Date("2026-01-01")` is UTC midnight and shows the previous day west of UTC. The initial list fetch sets state only in promise callbacks (lint rule `react-hooks/set-state-in-effect`).
- Verified end-to-end in the browser: the real form created 2026 (auto-current), 2025 and 2027; an overlapping "2026-27" was rejected with the message shown in the UI; "Set as current" switched 2026 → 2027 → 2026 with exactly one current year each time; deleting the current year returned 409; a throwaway year was deleted via the UI; the dashboard shows "Current session: 2026".
- Dev DB now holds 2025, **2026 (current)** and 2027.
