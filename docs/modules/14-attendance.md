# Module 14 — Attendance

- **Status:** ⬜ Not started
- **Phase:** Phase 3 — Academic Operations
- **Completed on:** —
- **Owner agent:** `module-builder`

> Daily and bulk student attendance (Present / Absent / Late / Leave) per class-section, with edit, monthly views, percentage, and class/student reports; the same concept covers teacher/employee attendance.

## Scope

Includes (spec §14): mark daily attendance for a section, bulk-mark a whole section in one request, edit a day's attendance, monthly attendance view, attendance-percentage calculation, class attendance report and per-student attendance report. Teacher/employee attendance reuses the same `attendances` + `attendance_details` structure keyed to the teacher/employee instead of a student.

Excludes: leave-application approval workflow, biometric/RFID device integration, and SMS absence alerts (deferred to Communication, spec §36).

## Dependencies

`13 — Enrollments` (student ↔ class/section membership), `12 — Teachers` (marker identity + assigned-class scope). Requires `06 — Classes` and `08 — Sections` transitively.

## Database (tables owned / referenced)

- `attendances` — id, class_id (FK), section_id (FK), date, taken_by (FK users), academic_year_id (FK), timestamps. Unique constraint on (section_id, date) so a section's daily sheet exists once. Index (section_id, date).
- `attendance_details` — id, attendance_id (FK, cascade), student_id (FK), status enum(Present, Absent, Late, Leave), remark nullable, timestamps. Unique (attendance_id, student_id) prevents duplicate student rows. Index (student_id, date via attendance) per spec §54 — carry a denormalized `date` column indexed as (student_id, date) for fast student reports.
- References: `student_enrollments`, `teachers`/`employees`.

## Permissions

Introduces `attendance.view`, `attendance.create`, `attendance.edit`. Two-layer authorization (spec §52): the permission is necessary but not sufficient — a teacher may only view/create/edit attendance for a class-section they are assigned to. Admin/principal bypass the assignment scope via policy.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /attendance | attendance.view | paginated; filter by class, section, date |
| POST | /attendance/bulk | attendance.create | marks whole section in one request |
| PUT | /attendance/{id} | attendance.edit | edit a day's sheet (scoped) |
| GET | /attendance/monthly | attendance.view | monthly grid + percentage |
| GET | /students/{id}/attendance | attendance.view | per-student report + % |

## Backend tasks

- [ ] Migrations + models (FKs, enum status, unique + composite indexes, soft deletes)
- [ ] Form Requests (`BulkAttendanceRequest`, `EditAttendanceRequest`) with `authorize()` checking assigned section
- [ ] `AttendancePolicy` — record-level check that marker owns the class-section (two-layer)
- [ ] `MarkAttendanceAction` / `AttendanceReportService` (percentage + monthly aggregation; no logic in controllers)
- [ ] `AttendanceResource` for response shaping
- [ ] Routes under `/api` with JWT + `permission:` middleware
- [ ] Idempotent upsert so re-posting the same date+student updates, never duplicates

## Frontend tasks

- [ ] Teacher: attendance page listing assigned-section roster with bulk status toggles
- [ ] Admin: class attendance report + monthly view (paginated)
- [ ] Typed `attendanceService` in `services/` (bulk, edit, monthly, student report)
- [ ] Role-appropriate visibility (teacher sees only assigned sections)

## Acceptance criteria

- [ ] A whole section is marked in a single `POST /attendance/bulk` request
- [ ] A teacher marking a non-assigned section is blocked with HTTP 403 (two-layer)
- [ ] Duplicate (date, student) is prevented by unique constraint + upsert
- [ ] Monthly percentage is computed correctly server-side
- [ ] Tests written and passing (bulk mark, 403 scope, duplicate guard, percentage)
- [ ] Security review passed (assignment scope enforced in policy, not just permission)

## Notes

Denormalizing `date` onto `attendance_details` trades a little write cost for the (student_id, date) index spec §54 calls out. Teacher attendance shares the tables; scope its marker to HR/admin. Percentage = present-equivalent days / working days for the range.
