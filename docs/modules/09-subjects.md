# Module 09 — Subjects

- **Status:** ⬜ Not started
- **Phase:** Phase 2 — Academic Core
- **Completed on:** —
- **Owner agent:** `module-builder`

> Manages subjects and their assignment to classes (spec §13 class subjects) via the `class_subjects` pivot, with an optional subject teacher.

## Scope

Includes: CRUD for subjects, and assigning/unassigning subjects to classes through a pivot that can also record an optional teacher. A subject may be assigned to many classes; a class may have many subjects. Listing the subjects of a class (class-subject list).

Excludes: marks, exam subjects, and routine slots (later modules). The subject-teacher link here is the source of truth that Routine/Marks scope against (spec §52), but scheduling itself is out of scope.

## Dependencies

- `07 — Classes` (subjects are assigned to classes). Optional teacher reference is nullable until Module 12 — Teachers exists.

## Database (tables owned / referenced)

- `subjects` — `id`, `name`, `code` (nullable, unique when present), `type` (e.g. theory/practical, nullable), timestamps. Index on `code`.
- `class_subjects` (pivot) — `id`, `class_id` (FK → `classes`), `subject_id` (FK → `subjects`), `teacher_id` (nullable FK → `teachers`), timestamps. Unique (`class_id`,`subject_id`); indexes on each FK.
- References: `classes`, `teachers` (nullable).

## Permissions

Spatie permissions: `subject.view`, `subject.create`, `subject.edit`, `subject.delete`. Assigning subjects to classes is gated by `subject.edit`. Admin/Principal manage.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /subjects | subject.view | paginated list |
| POST | /subjects | subject.create | create |
| GET | /subjects/{id} | subject.view | show |
| PUT | /subjects/{id} | subject.edit | update |
| DELETE | /subjects/{id} | subject.delete | blocked if assigned/used |
| GET | /classes/{classId}/subjects | subject.view | class-subject list (paginated) |
| POST | /classes/{classId}/subjects | subject.edit | assign subject (+ optional teacher) |
| DELETE | /classes/{classId}/subjects/{subjectId} | subject.edit | unassign |

## Backend tasks

- [ ] Migrations + `Subject` model and `class_subjects` pivot (FKs, unique, indexes)
- [ ] Form Requests (unique code; valid class/subject/teacher ids; `authorize()`)
- [ ] Policy (record-level authorization)
- [ ] Action/Service for assign/unassign (no logic in controllers)
- [ ] API Resources (subject + class-subject shapes) + routes with JWT + `permission:`

## Frontend tasks

- [ ] Admin pages `admin/subjects/` (CRUD) and class-subject assignment UI
- [ ] Typed API client methods in `services/`
- [ ] Assign/unassign UI with optional teacher select; validation + error states
- [ ] Paginated lists; role-appropriate visibility

## Acceptance criteria

- [ ] A subject can be assigned to multiple classes (many-to-many works)
- [ ] The class-subject list endpoint returns a class's assigned subjects, paginated
- [ ] Duplicate assignment of the same subject to a class is rejected
- [ ] Tests written and passing (feature tests for assignment + uniqueness)
- [ ] Security review passed (two-layer auth: permission + policy)

## Notes

`teacher_id` on the pivot is nullable and backfilled once teachers exist (Module 12); it later scopes routine and marks entry (spec §52). Deleting a subject is blocked while it is assigned. Academic config is cacheable (spec §60).
