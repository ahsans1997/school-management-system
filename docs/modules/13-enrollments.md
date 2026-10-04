# Module 13 — Enrollments

- **Status:** ⬜ Not started
- **Phase:** Phase 2 — Academic Core
- **Completed on:** —
- **Owner agent:** `module-builder`

> Places students into a class + section for an academic year (spec §13), assigns roll, and handles bulk promotion to the next class/year with section-capacity enforcement.

## Scope

Includes: enrolling a student into class+section for a year with a roll and status; bulk promotion that creates new-year enrollments for a cohort (moving to the next `numeric_level`); section-capacity checks at enrollment/promotion time (capacity defined in Module 08).

Excludes: the student, class, section, and year records themselves (Modules 11/07/08/06). Attendance, results, and fees consume enrollments but are later modules.

## Dependencies

- `11 — Students`, `08 — Sections`, `06 — Academic Years`.

## Database (tables owned / referenced)

- `student_enrollments` — `id`, `student_id` (FK → `students`), `academic_year_id` (FK → `academic_years`), `class_id` (FK → `classes`), `section_id` (FK → `sections`), `roll` (int), `status` (enum: active/transferred/promoted/left), timestamps. Composite index (`student_id`,`academic_year_id`) (spec §54); index on `section_id`. Unique (`student_id`,`academic_year_id`) — one enrollment per year; unique (`section_id`,`academic_year_id`,`roll`) — roll unique within section/year.
- References: `students`, `academic_years`, `classes`, `sections`.

## Permissions

Spatie permissions: `enrollment.view`, `enrollment.create`, `enrollment.edit`, `enrollment.delete`. Promotion (bulk) gated by `enrollment.create`. Admin/Principal manage.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /enrollments | enrollment.view | paginated; filter by year/class/section |
| POST | /enrollments | enrollment.create | enroll one student (capacity checked) |
| GET | /enrollments/{id} | enrollment.view | show |
| PUT | /enrollments/{id} | enrollment.edit | update (section/roll/status) |
| DELETE | /enrollments/{id} | enrollment.delete | guarded delete |
| POST | /enrollments/promote | enrollment.create | bulk promote cohort to next class/year |

## Backend tasks

- [ ] Migration + `StudentEnrollment` model (FKs, composite + unique indexes)
- [ ] Form Requests (valid ids; roll integer; status enum; `authorize()`)
- [ ] Policy (record-level authorization)
- [ ] `EnrollStudent` action with capacity check; `PromoteCohort` action in a transaction (no logic in controllers)
- [ ] Enforce one enrollment per student per year + unique roll within section/year
- [ ] API Resource + routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] Admin UI `admin/enrollments/` (paginated, filter by year/class/section)
- [ ] Typed API client methods in `services/`
- [ ] Enroll form (capacity feedback) + bulk promotion wizard with confirmation
- [ ] Validation + error states (capacity full, duplicate roll, already enrolled)

## Acceptance criteria

- [ ] A student can be enrolled only once per academic year (unique enforced)
- [ ] Roll is unique within a section for a given year
- [ ] Promotion creates new enrollments in the next class for the next year
- [ ] Section capacity is enforced — enrollment beyond capacity is rejected
- [ ] List endpoint is paginated; tests written and passing (enroll, promote, capacity, roll)
- [ ] Security review passed (two-layer auth; transactional bulk promotion)

## Notes

Capacity is read from `sections.capacity` (Module 08) and checked here. Because each enrollment stores both `class_id` and `section_id`, validate that the section belongs to that class (`sections.class_id`), so the two columns can't disagree. Decide whether shrinking a section's capacity below its current enrollment is refused or only flagged. Promotion is transactional (spec §55 pattern) so a partial cohort move rolls back. Composite index (`student_id`,`academic_year_id`) per spec §54 supports the common lookups. Status enum lives in `app/Enums` (spec §49).
