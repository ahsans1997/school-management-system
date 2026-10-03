# Module 07 — Classes

- **Status:** ✅ Complete
- **Phase:** Phase 2 — Academic Core
- **Completed on:** 2026-10-03

> Manages classes/grades (spec §13) — the second tier of the academic hierarchy, below Academic Year and above Section.

## Scope

Includes: CRUD for classes with a display name, a `numeric_level` for ordering and promotion, and optional `shift` and `medium` (spec §8 academic settings). Paginated listing ordered by level.

Excludes: sections (Module 08), subject assignment (Module 09), and enrollment/promotion (Module 13). Groups are deferred.

## Design decision — a shared catalogue, not per-year classes

Classes are a **stable catalogue shared across academic years**: "Class 6" is the same grade every year, and the year is pinned at **enrollment** (Module 13). This is the doc's own fallback, it matches the later modules (sections and class-subjects carry no year; enrollments do), and it avoids re-creating every class each year ("enter data once", spec §68). Consequently `classes` has **no** `academic_year_id`.

## Dependencies

- `06 — Academic Years` (year context arrives via enrollments in Module 13).

## Database (tables owned / referenced)

- `classes` — `id`, `name` (**unique**, ≤ 50), `numeric_level` (smallint, indexed; sort + promotion key, −5…20, negative for pre-primary), `shift` (nullable, `App\Enums\Shift`: morning/day/evening), `medium` (nullable, `App\Enums\Medium`: bangla/english), timestamps. Model `SchoolClass` (`class` is a PHP keyword).
- **Rule for every future table that references a class** (sections, class_subjects, enrollments…): `foreignId('class_id')->constrained('classes')->restrictOnDelete()` — the delete guard depends on it.

## Permissions

`class.view|create|edit|delete` (seeded in Module 02). Admin holds all; Principal holds `.view`. Teachers will see their classes through scoped portal endpoints (Module 34), not this admin catalogue.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /classes | class.view | paginated, ordered by `numeric_level`, then name |
| POST | /classes | class.create | create |
| GET | /classes/{id} | class.view | show |
| PUT | /classes/{id} | class.edit | update |
| DELETE | /classes/{id} | class.delete | 409 if referenced (FK restrict) |

## Backend tasks

- [x] Migration + `SchoolClass` model (unique name, indexed level, enum casts)
- [x] Form Requests: unique name, integer level in range, `Rule::enum` for shift/medium
- [x] Policy: N/A — school-wide reference data with no ownership; `permission:` middleware is the authorization layer
- [x] Actions for create/update/delete (no logic in controllers)
- [x] Delete guard via the new shared `App\Support\GuardedDelete` (FK restrict → 409); `DeleteAcademicYearAction` now uses it too
- [x] API Resource + routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [x] Admin page `admin/classes/` (paginated list, create form, inline edit, delete) + nav entry
- [x] Typed `classService`; shift/medium options mirror the backend enums
- [x] Validation + error states (duplicate name, level range, 409 on delete)
- [x] Role-appropriate visibility (create/edit/delete controls gated by permission)
- [x] Extracted shared `lib/errors.ts` (`errorMessage`) and `components/Pagination.tsx`; Academic Years moved onto them

## Acceptance criteria

- [x] Class name is unique (across the shared catalogue — see design decision); duplicates rejected
- [x] List endpoint is paginated (`?page&per_page`)
- [x] `numeric_level` persists and orders the list (KG = 0 sorts first; "Class 10" sorts after "Class 9", not alphabetically)
- [x] Tests written and passing (10 class tests, 49 assertions; 86 backend tests total)
- [x] Security review passed (permission gating; enum-validated input; FK-protected delete)

## Notes

`numeric_level` feeds promotion (next level = current + 1). A school running two shifts of the same grade names them distinctly (e.g. "Class 6 (Morning)"), so promotion in Module 13 can match on level + shift + medium. Academic config is cacheable (spec §60), but the class list is tiny and not yet hot — no caching added (YAGNI).

**Build notes (2026-10-03):**
- Verified end-to-end in the browser: the real form created "Class 6" (level 6, Morning, Bangla) and "KG" (level 0); a duplicate "Class 6" was refused with the message shown on the page; the list ordered KG → Class 1 … Class 10; the inline editor changed Class 6's shift to Day; a throwaway class was deleted through the UI.
- Dev DB now holds the catalogue **KG, Class 1 – Class 10** (Class 6: Day / Bangla).
