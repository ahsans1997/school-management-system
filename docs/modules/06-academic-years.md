# Module 06 — Academic Years

- **Status:** ⬜ Not started
- **Phase:** Phase 2 — Academic Core
- **Completed on:** —
- **Owner agent:** `module-builder`

> Manages academic years/sessions (spec §13) — the top of the academic hierarchy that every class, enrollment, exam, and fee cycle is scoped to.

## Scope

Includes: CRUD for academic years and a dedicated "set current" action that flags exactly one year as active. Each year carries a display name, start/end dates, and an `is_current` flag. Provides the year selector that downstream modules (classes, enrollments, results, fees) read from.

Excludes: classes, sections, enrollments, or promotion logic (Modules 07, 08, 13). Term/semester breakdown within a year is deferred to V2.

## Dependencies

- `03 — Users` (authenticated actor and permission subject).

## Database (tables owned / referenced)

- `academic_years` — `id`, `name` (e.g. "2026"), `start_date` (date), `end_date` (date), `is_current` (boolean), timestamps. Unique index on `name`; index on `is_current`. A DB-level constraint or transactional guard ensures at most one `is_current = true`.
- References: none (root of the hierarchy). Referenced by `classes`, `student_enrollments`.

## Permissions

Spatie permissions: `academic_year.view`, `academic_year.create`, `academic_year.edit`, `academic_year.delete`. Set-current is gated by `academic_year.edit`. Admin/Principal roles hold all; read-only roles get `.view`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /academic-years | academic_year.view | paginated list |
| POST | /academic-years | academic_year.create | create |
| GET | /academic-years/{id} | academic_year.view | show |
| PUT | /academic-years/{id} | academic_year.edit | update |
| DELETE | /academic-years/{id} | academic_year.delete | blocked if enrollments exist |
| POST | /academic-years/{id}/set-current | academic_year.edit | atomically sets the single current year |

## Backend tasks

- [ ] Migration + `AcademicYear` model (unique `name`, index `is_current`)
- [ ] Form Requests (validate `end_date` after `start_date`; `authorize()`)
- [ ] Policy (record-level authorization on view/edit/delete)
- [ ] `SetCurrentAcademicYear` action inside a transaction (unset others, set one)
- [ ] Delete guard: reject when the year has related `student_enrollments`
- [ ] API Resource + routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] Admin page `admin/academic-years/` (paginated list, create/edit/view)
- [ ] Typed API client methods in `services/`
- [ ] "Set as current" control with confirmation and current-year badge
- [ ] Validation + error states (date range, duplicate name)

## Acceptance criteria

- [ ] Exactly one year has `is_current = true` at all times; set-current is atomic
- [ ] A year with existing enrollments cannot be deleted (returns a clear 409/422)
- [ ] List endpoint is paginated (`?page&per_page`)
- [ ] Tests written and passing (feature tests for set-current uniqueness + delete guard)
- [ ] Security review passed (two-layer auth: permission + policy; input trust)

## Notes

`is_current` drives default scoping across the app; academic configuration is a good caching candidate (spec §60) with invalidation on set-current. No business logic in controllers — set-current lives in an action.
