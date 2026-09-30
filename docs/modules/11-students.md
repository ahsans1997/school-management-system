# Module 11 — Students

- **Status:** ⬜ Not started
- **Phase:** Phase 2 — Academic Core
- **Completed on:** —
- **Owner agent:** `module-builder`

> The student master record (spec §9) — profile, documents, photo, and lifecycle actions (archive, transfer, promote, ID card, certificates, import/export, search). Class/section membership is expressed through enrollments (Module 13).

## Scope

Includes: CRUD + view; archive via soft delete; transfer and promote hooks; ID-card and certificate generation; queued import/export; search and paginated listing. Fields per spec §9: student_id, admission_no, name, dob, gender, blood_group, roll, father/mother/guardian, phone, address, photo, previous school, documents.

Excludes: the enrollment mechanics of class+section+year assignment (Module 13 owns `student_enrollments`); attendance, marks, and fees (later modules). Online admission intake is Module 31.

## Dependencies

- `08 — Sections`, `10 — Guardians`, `06 — Academic Years` (current class/section shown via enrollment; guardians linked; year context).

## Database (tables owned / referenced)

- `students` — `id`, `student_id` (unique), `admission_no` (unique), `name`, `dob`, `gender`, `blood_group` (nullable), `roll` (nullable, canonical roll lives on enrollment), `father_name`, `mother_name`, `guardian_id` (nullable FK), `phone`, `address`, `photo` (nullable), `previous_school` (nullable), `documents` (json/related), `deleted_at` (soft delete), timestamps. Indexes: `admission_no`, `student_id` (spec §54).
- References: `guardians`, and (read) `sections`/`academic_years` via `student_enrollments` (Module 13).

## Permissions

Spatie permissions: `student.view`, `student.create`, `student.edit`, `student.delete`, `student.export`. Teacher/Parent access is narrowed by ownership policy (assigned/own children).

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /students | student.view | paginated + search (`?q&page&per_page`) |
| POST | /students | student.create | generates unique admission_no |
| GET | /students/{id} | student.view | show profile |
| PUT | /students/{id} | student.edit | update |
| DELETE | /students/{id} | student.delete | soft-delete (archive) |
| POST | /students/{id}/transfer | student.edit | transfer out / status change |
| POST | /students/import | student.create | queued import job |
| GET | /students/export | student.export | queued export (PDF/Excel/CSV) |
| GET | /students/{id}/id-card | student.view | ID card (queued PDF) |

## Backend tasks

- [ ] Migration + `Student` model (soft deletes; unique + indexed `admission_no`, `student_id`)
- [ ] Form Requests (photo MIME + max size; document validation; `authorize()`)
- [ ] Policy (record-level; teacher/parent ownership scoping)
- [ ] `GenerateAdmissionNo` + create action; transfer/promote actions (no logic in controllers)
- [ ] Import/Export + ID-card/certificate PDF as queued Jobs (spec §61)
- [ ] API Resource + routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] Admin pages `admin/students/` (paginated searchable list, create/edit/view, archive)
- [ ] Typed API client methods in `services/`
- [ ] Photo/document upload with client validation + error states
- [ ] Import/export UI (job status), ID-card/certificate download; role-appropriate views

## Acceptance criteria

- [ ] Creating a student generates a unique `admission_no` (collision-safe)
- [ ] Photo upload is validated (MIME + max size) and rejected otherwise
- [ ] List supports search and is paginated (`?q&page&per_page`)
- [ ] Import and export run as queued jobs (do not block the request)
- [ ] Delete soft-deletes (archives) — never hard-deletes
- [ ] Tests written and passing; security review passed (two-layer auth, upload validation)

## Notes

Canonical class/section/roll for a given year live on `student_enrollments` (Module 13); `students.roll` is a convenience mirror. Promotion is orchestrated by Module 13. Uploads follow spec §44/§51 (MIME + size). Enter-once/use-everywhere: this record feeds attendance, results, fees, portals (spec §68).
