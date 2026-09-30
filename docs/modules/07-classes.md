# Module 07 — Classes

- **Status:** ⬜ Not started
- **Phase:** Phase 2 — Academic Core
- **Completed on:** —
- **Owner agent:** `module-builder`

> Manages classes/grades (spec §13) — the second tier of the academic hierarchy, below Academic Year and above Section.

## Scope

Includes: CRUD for classes with a display name, a `numeric_level` for ordering and promotion logic, and optional `shift` and `medium` attributes (spec §8 academic settings). Paginated listing.

Excludes: sections (Module 08), subject assignment (Module 09), and enrollment/promotion (Module 13). Groups are deferred.

## Dependencies

- `06 — Academic Years` (classes are contextualized by the active/selected year).

## Database (tables owned / referenced)

- `classes` — `id`, `name` (e.g. "Class 6"), `numeric_level` (int, for ordering/promotion), `shift` (nullable), `medium` (nullable), timestamps. Unique constraint on the class per year context (see Notes); index on `numeric_level`.
- References: `academic_years` (year scoping/context).

## Permissions

Spatie permissions: `class.view`, `class.create`, `class.edit`, `class.delete`. Admin/Principal manage; teacher/read roles receive `.view`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /classes | class.view | paginated list |
| POST | /classes | class.create | create |
| GET | /classes/{id} | class.view | show |
| PUT | /classes/{id} | class.edit | update |
| DELETE | /classes/{id} | class.delete | blocked if sections/enrollments exist |

## Backend tasks

- [ ] Migration + `SchoolClass` model (avoid reserved `Class`); FKs, indexes
- [ ] Form Requests (unique name per year context; `numeric_level` integer; `authorize()`)
- [ ] Policy (record-level authorization)
- [ ] Action/Service for create/update (no logic in controllers)
- [ ] Delete guard: reject when dependent sections or enrollments exist
- [ ] API Resource + routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] Admin page `admin/classes/` (paginated list, create/edit/view)
- [ ] Typed API client methods in `services/`
- [ ] Forms with validation + error states; `numeric_level`, optional shift/medium
- [ ] Role-appropriate visibility (admin manage, teacher view)

## Acceptance criteria

- [ ] Class name is unique within its year context; duplicates rejected
- [ ] List endpoint is paginated (`?page&per_page`)
- [ ] `numeric_level` persists and orders the list
- [ ] Tests written and passing (feature tests for uniqueness + pagination)
- [ ] Security review passed (two-layer auth: permission + policy)

## Notes

Uniqueness is enforced per year context; if classes are shared across years, uniqueness is on `name` alone and year scoping happens at enrollment (Module 13). `numeric_level` feeds promotion (next level = current + 1). Model named `SchoolClass` to avoid the PHP `Class` keyword. Academic config is cacheable (spec §60).
