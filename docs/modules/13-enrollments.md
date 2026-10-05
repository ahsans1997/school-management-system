# Module 13 — Enrollments

- **Status:** 🔵 In review: built, tested and verified through the live API. The admin UI builds, lints and type-checks but still needs a browser click-through (see Build notes).
- **Phase:** Phase 2 — Academic Core
- **Completed on:** —
- **Owner agent:** `module-builder`

> Places students into a section (and so a class) for an academic year with a roll (spec §13). Handles transfers, leaving and cohort promotion, enforces section capacity, and scopes teachers to their own students (spec §52).

## Scope

Included:
- **Enrollment:** a student in a section for a year, with a roll and a status.
- **Rules:** one enrollment per year, rolls unique per section/year, and section capacity.
- **Changes:** moving section, changing the roll, and status changes. Transfer out, left and graduated each carry an end date and reason; back to active clears them.
- **Promotion:** chosen students move into a section one class up, for a later year.
- **Carried over from Module 11:**
  - Transfer, done as a status change on the enrollment, not a new student field.
  - Class, section and roll shown on the student list and profile, with enrollment history.
  - **Teacher scoping:** a teacher sees only the students of their own sections.
- **Section capacity:** can no longer drop below the students enrolled in it.

**Split out to Module 13b** (see [13b-student-records-tools.md](13b-student-records-tools.md)): ID cards, certificates and import/export. They need finished enrollment data and are self-contained outputs, and this module was already the largest in Phase 2.

Excludes: attendance, results, fees (they consume enrollments, later modules).

## Design decisions

- **The class comes from the section.** The API takes `section_id` only, and `class_id` is filled in server-side. A composite FK `(section_id, class_id) → sections(id, class_id)` makes the database refuse any row where the two disagree (tested). This satisfies the Module 08 rule for tables storing both.
- **Rules live in `App\Actions\Enrollment\Enrollments`,** in transactions that lock the affected section rows, in id order so opposite moves can't deadlock:
  - One per student/year (unique index + a friendly 422 naming the existing class).
  - Roll unique per section/year across *all* statuses, so a departed student's roll isn't reused that year. Rolls are sequential when left blank.
  - Capacity counts **active enrollments of non-archived students**, so a transfer, leaving or archiving frees the seat.
- **Status lifecycle:** `active`, `promoted`, `transferred`, `left`, `graduated` (`App\Enums\EnrollmentStatus`). `graduated` was added for the final class. Leaving is a status change. `DELETE` is only for mistakes, guarded by FK RESTRICT once attendance/results reference enrollments.
- **Promotion** (`POST /enrollments/promote`) is all or nothing:
  - The target must be a later year and a class exactly one level up.
  - Selected students must be active in the source section.
  - Nobody may already be enrolled for the target year.
  - The target section must have room for everyone.
  - Old enrollments become `promoted` (ended today); new ones are numbered in the old roll order. Students left unticked (held back) stay as they are.
- **Teacher scoping (ownership, spec §52):**
  - `Student::visibleTo($user)`: when the caller's audience is Teacher, students are limited to those **actively enrolled this year in a section the teacher teaches or is class teacher of** (from `teaching_assignments` / `sections.class_teacher_id`).
  - A Teacher-role login not linked to a teacher record sees nobody.
  - Outside their sections a profile returns 404, not 403. Everyone else past the permission check sees all students.
- **Section capacity can't shrink** below the busiest year's active enrollment (422 naming the count and year).
- **Current enrollment:** `Student::currentEnrollment()` reads the current academic year (`AcademicYear::current()`). Lists default to the current year.

## Database

- `student_enrollments` — `student_id` (FK), `academic_year_id` (FK, RESTRICT: a year with enrollments can't be deleted), `class_id` (FK), `section_id` (+ composite FK with `class_id`), `roll` (smallint), `status`, `ended_on`, `exit_reason`, timestamps.
  - Unique (`student_id`,`academic_year_id`) (spec §54) and (`section_id`,`academic_year_id`,`roll`).
  - Index (`academic_year_id`,`class_id`).
- `sections` gains unique (`id`,`class_id`) as the composite-FK target.

## Permissions

`enrollment.view|create|edit|delete` (seeded in Module 02). Admin holds all; Principal `.view`. Promotion needs `enrollment.create`. Teachers have no enrollment permissions: they see their students through the scoped student list.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /enrollments | enrollment.view | `?academic_year_id` (default current), `?class_id`, `?section_id`, `?status`, `?search`; ordered class → section → roll; with a section, `seats: {capacity, taken}` |
| POST | /enrollments | enrollment.create | `{student_id, academic_year_id, section_id, roll?}`; 422 for already enrolled / roll taken / section full / archived student |
| GET | /enrollments/{id} | enrollment.view | |
| PUT | /enrollments/{id} | enrollment.edit | `{section_id, roll?, status, ended_on?, exit_reason?}`: move, re-roll, transfer out, back to active |
| DELETE | /enrollments/{id} | enrollment.delete | mistakes only; guarded |
| POST | /enrollments/promote | enrollment.create | `{from_year_id, from_section_id, to_year_id, to_section_id, student_ids[]}`; all or nothing |

Students: `GET /students` rows carry `current_enrollment` and accept `?class_id` / `?section_id`. `GET /students/{id}` adds `enrollments` (newest year first). Both are teacher-scoped.

## Backend tasks

- [x] Migration + `StudentEnrollment` (composite FK, unique indexes), `EnrollmentStatus` enum
- [x] Form Requests (ids incl. "not archived", roll range, status enum, promotion payload)
- [x] Ownership: teacher scope on the student list/profile (`Student::visibleTo`); permission per route elsewhere
- [x] `Enrollments` actions: enroll (capacity + roll + one-per-year), update (move / re-roll / status), promote (transactional)
- [x] Section capacity floor; student resources show current enrollment + history
- [x] API Resource + routes

## Frontend tasks

- [x] `admin/enrollments` (year / class / section filters, seats indicator, find-and-enroll, inline roll/status/reason edit, promotion panel with per-student ticks and confirmation) + nav entry
- [x] Student list "Class" column (section + roll, or "Not enrolled"); student profile Enrollment panel (current place, transfer out / mark as left, enroll for the current year, history)
- [x] Typed `enrollmentService` + `types/enrollment.ts`
- [ ] Browser click-through (see Build notes)

## Acceptance criteria

- [x] A student can be enrolled only once per academic year (unique enforced)
- [x] Roll is unique within a section for a given year
- [x] Promotion creates new enrollments in the next class for the next year
- [x] Section capacity is enforced: enrollment beyond capacity is rejected (and a section can't shrink below its students)
- [x] List endpoint is paginated; tests written and passing (20 enrollment/promotion/teacher-scope tests; 196 backend tests total)
- [x] Security review: two-layer auth (permission per route + teacher ownership scope on students); class derived server-side and DB-checked; transactional, all-or-nothing promotion with section locks

## Notes

Capacity is read from `sections.capacity` (Module 08). Promotion is transactional (spec §55 pattern). Later modules should iterate **active enrollments of the current year** for "who is in this section", and authorize teachers through `teaching_assignments` / `Student::visibleTo`.

**Build notes (2026-10-05):**
- **Not yet clicked through in a browser.** The in-app browser tools were still blocked for this session (same reason as Module 12). The new screens compile, lint and type-check and call the endpoints verified below. **To finish:** open `/admin/enrollments` (and a student profile's Enrollment panel), click through enroll → edit → promote → transfer, then mark ✅.
- Verified live through the API on MariaDB:
  - **Enroll:** added students Rafi Karim (`2026-0002`) and Anika Rahman (`2026-0003`). Enrolled Nusrat (6A roll 1), Rafi (6A roll 2) and Anika (6B roll 1) for 2026.
  - **Refusals:** re-enrolling Nusrat into 6B gave "already enrolled for 2026 (Class 6 A)". Giving Rafi roll 1 gave "Roll 1 is already taken". Shrinking 6A to capacity 1 gave "2 students are enrolled…".
  - **Lists:** the 6A list ordered by roll with `seats: 2 / 40`, and the students list shows section and roll.
  - **Teacher scope:** Farhana (6A + 6B) sees all three. The demo teacher (6A English) sees Nusrat and Rafi only, gets 404 on Anika's profile, and 403 on `/enrollments`.
  - **Promotion:** into 6B (same level) was refused. Rafi → Class 7 A for 2027 succeeded, and his history showed 2027 active / 2026 promoted. Reverted afterwards (deleted the 2027 enrollment, 2026 back to active).
  - **Transfer:** Anika → transferred ("Moved to Chittagong", today), which freed 6B's seat (0 taken). Then back to active, with the end date and reason cleared.
  - One probe of mine accidentally created a 2027 enrollment for Anika (I sent the wrong year). It was deleted straight away.
- Dev DB 2026 enrollments: Class 6 A: Nusrat Jahan (1), Rafi Karim (2); Class 6 B: Anika Rahman (1). New section Class 7 A (40). No 2027 enrollments.
