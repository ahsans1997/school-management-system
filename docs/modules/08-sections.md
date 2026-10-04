# Module 08 — Sections

- **Status:** ✅ Complete
- **Phase:** Phase 2 — Academic Core
- **Completed on:** 2026-10-04
- **Owner agent:** `module-builder`

> Manages sections (spec §13) — the divisions within a class (A/B/C), each with a seating capacity used later to cap enrollment.

## Scope

Includes: CRUD for sections nested under a class, each with a `name` (e.g. A/B/C) and a `capacity`. Listing sections of a given class.

Excludes: enrollment and the actual capacity enforcement at enrollment time (Module 13 — capacity is stored here, checked there). Section-level class teacher assignment is deferred to Teachers (Module 12) / Routine.

## Dependencies

- `07 — Classes` (a section always belongs to a class).

## Database (tables owned / referenced)

- `sections` — `id`, `class_id` (FK → `classes`, **ON DELETE RESTRICT**), `name` (≤ 50, e.g. "A"), `capacity` (unsigned int), timestamps. Unique (`class_id`, `name`); that composite index's leading column also serves as the `class_id` index. Model `Section` (`schoolClass()` belongsTo; `SchoolClass::sections()` hasMany).
- Because of the FK, **a class that still has sections can't be deleted** (409 via `GuardedDelete`).
- **Rule for every future table that references a section** (enrollments, attendances, routines, exam_subjects…): `foreignId('section_id')->constrained()->restrictOnDelete()`.
- References: `classes` (parent).

## Permissions

`section.view|create|edit|delete` (seeded in Module 02). Admin holds all; Principal holds `.view`. **Deviation:** the plan said teachers get `.view`; they don't. This matches Classes (Module 07): a teacher can't open the class catalogue either. Teachers see *their* sections through scoped portal endpoints (Modules 12/34), not this admin screen.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /classes/{classId}/sections | section.view | paginated, ordered by name; response also carries the parent `class` |
| POST | /classes/{classId}/sections | section.create | create under the class in the route (a body `class_id` is ignored) |
| GET | /sections/{id} | section.view | show, includes `class` |
| PUT | /sections/{id} | section.edit | name + capacity only — a section never moves to another class |
| DELETE | /sections/{id} | section.delete | 409 while referenced (FK restrict — enrollments from Module 13) |

## Backend tasks

- [x] Migration + `Section` model (FK `class_id` restrict, unique (`class_id`,`name`))
- [x] Form Requests: name unique within the class (custom message), `capacity` integer 1–1000; the parent class comes from the route, never the body (`class_id` is also `$guarded`)
- [x] Policy: N/A — school-wide reference data with no ownership (same as Classes). The parent context is enforced structurally: nested routes resolve the class (404 if missing) and `class_id` is immutable after creation
- [x] Actions for create/update/delete (no logic in controllers)
- [x] Delete guard via `GuardedDelete` (FK restrict → 409)
- [x] API Resource + nested routes under `/api` with JWT + `permission:` middleware
- [x] Class list now returns `sections_count` (`withCount`)

## Frontend tasks

- [x] Admin UI at `admin/classes/[classId]/sections/` (paginated list, create form, inline edit, delete, "class doesn't exist" state); the class list links to it with a "N sections" column
- [x] Typed `sectionService` + `types/section.ts`
- [x] Validation + error states (duplicate name within class, capacity range, 409 on delete)
- [x] Role-appropriate visibility (create/edit/delete controls gated by permission; the sections column only shows with `section.view`)

## Acceptance criteria

- [x] Every section belongs to exactly one class (FK enforced; class fixed at creation)
- [x] Section name is unique within its class (the same name is fine in another class)
- [x] `capacity` is stored and exposed for the enrollment cap (enforced in Module 13)
- [x] List endpoint is paginated (`?page&per_page`)
- [x] Tests written and passing (10 section tests; class tests now cover "a class with sections can't be deleted" and `sections_count`)
- [x] Security review passed (permission gating per route; parent from route only; mass-assignment guarded; bounded input; FK-protected delete; 404s no longer leak model class names)

## Notes

Capacity is defined here but enforced at enrollment (Module 13) so a section can be resized without touching enrollment records. Module 13 should decide whether shrinking capacity below the current enrollment count is refused or only flagged.

**Later tables that store both `class_id` and `section_id`** (enrollments, attendances, routines, exam_subjects) must validate that the section belongs to that class, otherwise the two columns can disagree.

**Build notes (2026-10-04):**
- **Shared fixes made along the way (they apply to every module):**
  - **404s:** an API 404 for a missing record now returns `{"message":"Not found."}` instead of Laravel's `No query results for model [App\Models\…] 7`, which exposed internal class names (`bootstrap/app.php`).
  - **Page size:** list endpoints read `?per_page` through the new `App\Support\PerPage::from($request)` (default 25, max 100). Before this, `per_page=-1` crashed with a 500 that showed the SQL error, and any large value returned a whole table in one response. All five list endpoints switched (`tests/Feature/PerPageTest.php`).
- Verified end-to-end in the browser as the demo admin: from the class list "0 sections" link → "Class 6 — sections"; created A (40), B (35), C (30) through the form; a duplicate A was refused with "This class already has a section with that name."; the inline editor changed B to 45; C was deleted through the UI; back on the class list Class 6 showed "2 sections" and deleting it was refused with the 409 message; `/admin/classes/9999/sections` shows "This class doesn't exist."; the 375px layout has no horizontal overflow.
- Dev DB now holds **Class 6: A (40), B (45)**.
