# Module 09 — Subjects

- **Status:** ✅ Complete
- **Phase:** Phase 2 — Academic Core
- **Completed on:** 2026-10-04
- **Owner agent:** `module-builder`

> Manages subjects and their assignment to classes (spec §13 class subjects) via the `class_subjects` pivot. The optional subject teacher moves to Module 12.

## Scope

Includes: CRUD for the subject catalogue, and assigning/unassigning subjects to classes. A subject may be taught in many classes; a class may have many subjects. Listing the subjects of a class.

Excludes: marks, exam subjects, and routine slots (later modules). **The subject teacher is not here** — see the design decision below.

## Design decision — the subject teacher arrives with Teachers (Module 12)

The plan put a nullable `teacher_id` on `class_subjects`, but the `teachers` table doesn't exist yet. A column added now couldn't carry its FK and would accept any number. So `class_subjects` has no teacher column, and Module 12 adds the link with a real FK. Module 12's doc also flags a grain question: subject teachers often differ per **section** (6A Maths vs 6B Maths), which a per-class `teacher_id` can't express.

## Dependencies

- `07 — Classes` (subjects are assigned to classes).

## Database (tables owned / referenced)

- `subjects` — `id`, `name` (**unique**, ≤ 100), `code` (nullable, ≤ 20, unique when present — NULLs don't collide), `type` (nullable, `App\Enums\SubjectType`: theory / practical / theory_practical), timestamps.
- `class_subjects` — `id`, `class_id` (FK → `classes`, RESTRICT), `subject_id` (FK → `subjects`, RESTRICT), timestamps. Unique (`class_id`,`subject_id`); its leading column covers `class_id`, and the FK index covers `subject_id`. Model `ClassSubject` (its own model so later modules can hang data on an assignment).
- Relations: `SchoolClass::subjects()` / `Subject::classes()` (belongsToMany through `class_subjects`).
- Because of RESTRICT, an **assigned subject can't be deleted** and a **class with subjects can't be deleted** (both 409 via `GuardedDelete`).

## Permissions

`subject.view|create|edit|delete` (seeded in Module 02). Assigning/unassigning is gated by `subject.edit`. Admin holds all; Principal **and Teacher** hold `.view` (the seeded matrix), so both can read the catalogue and a class's subjects but change nothing.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /subjects | subject.view | paginated, ordered by name, with `classes_count` |
| POST | /subjects | subject.create | create |
| GET | /subjects/{id} | subject.view | show, with `classes_count` |
| PUT | /subjects/{id} | subject.edit | update |
| DELETE | /subjects/{id} | subject.delete | 409 while assigned/used |
| GET | /classes/{classId}/subjects | subject.view | paginated, ordered by subject name; each row embeds `subject`; response carries the parent `class` |
| POST | /classes/{classId}/subjects | subject.edit | body `{subject_id}`; 422 if missing or already assigned |
| DELETE | /classes/{classId}/subjects/{subjectId} | subject.edit | unassign; 404 if not assigned to that class |

## Backend tasks

- [x] Migration + `Subject` / `ClassSubject` models, `SubjectType` enum, FKs (RESTRICT), unique constraints
- [x] Form Requests: unique name and code, enum type; assignment validates `subject_id` exists and isn't already assigned (custom messages); the class comes from the route
- [x] Policy: N/A — school-wide reference data with no ownership (same as Classes/Sections)
- [x] Actions: create/update/delete subject, assign/unassign (no logic in controllers)
- [x] API Resources (`SubjectResource`, `ClassSubjectResource`) + routes with JWT + `permission:`
- [x] Class list now returns `subjects_count`

## Frontend tasks

- [x] Admin page `admin/subjects/` (catalogue CRUD with inline edit, "Taught in N classes") + nav entry
- [x] Class-subject screen `admin/classes/[classId]/subjects/` (assign from a catalogue picker that hides already-assigned subjects, remove, "class doesn't exist" state); the class list links to it with an "N subjects" column
- [x] Typed `subjectService` + `types/subject.ts`; shared `lib/options.ts` (`labelOf`)
- [x] Validation + error states (duplicate name/code, duplicate assignment, 409 on delete); paginated lists; controls gated by permission

## Acceptance criteria

- [x] A subject can be assigned to multiple classes (many-to-many works)
- [x] The class-subject list endpoint returns a class's assigned subjects, paginated
- [x] Duplicate assignment of the same subject to a class is rejected
- [x] Tests written and passing (10 subject + 9 class-subject tests; 116 backend tests total)
- [x] Security review passed (permission gating per route incl. `subject.edit` for assignment; class from route only; validated ids; FK-protected deletes)

## Notes

The picker loads up to 100 subjects (the API page-size cap) and says so if the catalogue is larger; add a search to it if a school ever gets there. Academic config is cacheable (spec §60), but these lists are small and not yet hot — no caching added.

**Build notes (2026-10-04):**
- Verified end-to-end in the browser as the demo admin. Through the form I created Bangla (101, Theory), Mathematics (109, Theory), Science (127, Theory + practical) and English (no code/type). A duplicate code 101 was refused with "The code has already been taken." The inline editor gave English code 107 / Theory.
- On Class 6's subjects screen I assigned all four; the list sorted by name and the picker dropped each assigned subject. I removed Science through the UI and it reappeared in the picker.
- Via the API on MariaDB: a duplicate assignment returned the custom 422 message; Bangla and Mathematics were also assigned to Class 7 (many-to-many); unassigning a subject a class doesn't have returned 404 "Not found."
- The Subjects screen then showed "2 classes" for Bangla, and deleting it was refused with the 409 message. No horizontal overflow at 375px on the three affected screens.
- Dev DB now holds subjects **Bangla, English, Mathematics, Science**; Class 6 → Bangla/English/Mathematics, Class 7 → Bangla/Mathematics.
