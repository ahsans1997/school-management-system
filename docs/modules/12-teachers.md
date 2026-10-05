# Module 12 — Teachers

- **Status:** ⬜ Not started
- **Phase:** Phase 2 — Academic Core
- **Completed on:** —
- **Owner agent:** `module-builder`

> The teacher master record (spec §11) — profile, documents, salary, and class/subject assignments that later scope routine, attendance, and marks entry.

## Scope

Includes: CRUD for teachers with fields per spec §11 (employee_id, name, photo, qualification, designation, department, joining_date, phone, email, address, documents, salary); assigning classes and subjects to a teacher (via the `class_subjects` pivot from Module 09 and/or a class-teacher link).

Excludes: teacher attendance, routine/timetable, and marks entry (Modules 14/15/17) — this module supplies the assignment data those consume. Public teacher website profiles are Module 28. Payroll is V2.

## Dependencies

- `09 — Subjects` (subject assignment writes `teacher_id` on `class_subjects`). Optional login account reuses `03 — Users`.

## Database (tables owned / referenced)

- `teachers` — `id`, `user_id` (nullable FK → `users`), `employee_id` (unique), `name`, `photo` (nullable), `qualification`, `designation`, `department`, `joining_date` (date), `phone`, `email` (nullable), `address` (nullable), `documents` (json/related), `salary` (`decimal(15,2)`), timestamps. Index on `employee_id`, `department`.
- References: `users`; writes `class_subjects.teacher_id` (Module 09); optional class-teacher assignment on `sections`/`classes`.

## Permissions

Spatie permissions: `teacher.view`, `teacher.create`, `teacher.edit`, `teacher.delete`. Assignment endpoints gated by `teacher.edit`. Admin/Principal/HR manage.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /teachers | teacher.view | paginated list |
| POST | /teachers | teacher.create | create (+ optional login account) |
| GET | /teachers/{id} | teacher.view | show profile + assignments |
| PUT | /teachers/{id} | teacher.edit | update |
| DELETE | /teachers/{id} | teacher.delete | soft/guarded delete |
| POST | /teachers/{id}/subjects | teacher.edit | assign subject(s) to class(es) |
| POST | /teachers/{id}/classes | teacher.edit | assign class-teacher role |

## Backend tasks

- [ ] Migration + `Teacher` model (unique `employee_id`; `salary` `decimal(15,2)`)
- [ ] Form Requests (photo/document MIME + max size; contact validation; `authorize()`)
- [ ] Policy (record-level authorization)
- [ ] Actions/Services: create-with-optional-user, subject/class assignment (no logic in controllers)
- [ ] Assign Teacher role via Spatie when a login account is created
- [ ] API Resource + routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] Admin pages `admin/teachers/` (paginated list, create/edit/view)
- [ ] Typed API client methods in `services/`
- [ ] Class/subject assignment UI; photo/document upload with validation + error states
- [ ] Role-appropriate visibility (teacher portal reads own profile later)

## Acceptance criteria

- [ ] Teacher CRUD works with unique `employee_id`
- [ ] Subject/class assignment persists (written to `class_subjects`/class-teacher link)
- [ ] Photo and documents are validated (MIME + max size) and rejected otherwise
- [ ] Assignment data is readable by routine/marks modules to scope teacher access (spec §52)
- [ ] List endpoint is paginated; tests written and passing
- [ ] Security review passed (two-layer auth: permission + policy; upload validation)

## Notes

**Carried over from Module 09:** `class_subjects` was built *without* `teacher_id`, because a column added before the `teachers` table existed couldn't have its FK. This module adds the subject-teacher link. Decide its grain first: a class with several sections often has a different teacher for the same subject in each section (Class 6 A Maths vs Class 6 B Maths). A single `class_subjects.teacher_id` can't express that. A separate `section_id + subject_id → teacher_id` assignment (unique on section + subject) can, and attendance, routine and marks scoping (spec §52) work per section anyway.

`salary` is money → `decimal(15,2)` (spec §53), never float. Assignments are the source of truth for the teacher's authorization scope in later modules (a teacher enters marks only for assigned class/subject — spec §52). Enter-once/use-everywhere: this record feeds routine, portal, and public profile (spec §68).

**Carried over from Module 11:** the Teacher role holds `student.view`, so a teacher can currently list every student through `/api/students`. Once this module's assignments exist (and Module 13's enrollments), scope the student list and profile to the teacher's sections: a query scope plus a `StudentPolicy`. That's the ownership layer spec §52 asks for. Teachers also get logins the same way guardians do: register `'teacher' => Teacher::class` in the morph map and create the login through `CreateUserAction` (see `CreateGuardianAccountAction`).
