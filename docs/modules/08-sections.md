# Module 08 — Sections

- **Status:** ⬜ Not started
- **Phase:** Phase 2 — Academic Core
- **Completed on:** —
- **Owner agent:** `module-builder`

> Manages sections (spec §13) — the divisions within a class (A/B/C), each with a seating capacity used later to cap enrollment.

## Scope

Includes: CRUD for sections nested under a class, each with a `name` (e.g. A/B/C) and a `capacity`. Listing sections of a given class.

Excludes: enrollment and the actual capacity enforcement at enrollment time (Module 13 — capacity is stored here, checked there). Section-level class teacher assignment is deferred to Teachers (Module 12) / Routine.

## Dependencies

- `07 — Classes` (a section always belongs to a class).

## Database (tables owned / referenced)

- `sections` — `id`, `class_id` (FK → `classes`, indexed, cascade-restricted), `name` (e.g. "A"), `capacity` (unsigned int), timestamps. Unique constraint on (`class_id`, `name`); index on `class_id`.
- References: `classes` (parent).

## Permissions

Spatie permissions: `section.view`, `section.create`, `section.edit`, `section.delete`. Admin/Principal manage; teacher/read roles receive `.view`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /classes/{classId}/sections | section.view | paginated, scoped to class |
| POST | /classes/{classId}/sections | section.create | create under class |
| GET | /sections/{id} | section.view | show |
| PUT | /sections/{id} | section.edit | update |
| DELETE | /sections/{id} | section.delete | blocked if enrollments exist |

## Backend tasks

- [ ] Migration + `Section` model (FK `class_id`, unique (`class_id`,`name`), index)
- [ ] Form Requests (name unique within class; `capacity` positive int; `authorize()`)
- [ ] Policy (record-level authorization; verify parent class ownership/context)
- [ ] Action/Service for create/update (no logic in controllers)
- [ ] Delete guard: reject when the section has enrollments
- [ ] API Resource + nested routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] Admin UI under `admin/classes/[classId]/sections/` (paginated list, CRUD)
- [ ] Typed API client methods in `services/`
- [ ] Forms with validation + error states (name, capacity)
- [ ] Role-appropriate visibility (admin manage, teacher view)

## Acceptance criteria

- [ ] Every section belongs to exactly one class (FK enforced)
- [ ] Section name is unique within its class
- [ ] `capacity` is stored and exposed for the enrollment cap (enforced in Module 13)
- [ ] List endpoint is paginated (`?page&per_page`)
- [ ] Tests written and passing (feature tests for nesting + uniqueness)
- [ ] Security review passed (two-layer auth: permission + policy)

## Notes

Capacity is defined here but enforced at enrollment (Module 13) so a section can be resized without touching enrollment records. Deletion is restricted while enrollments reference the section. Nested routes keep the class → section relationship explicit.
