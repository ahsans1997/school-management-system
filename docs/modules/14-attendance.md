# Module 14 — Attendance

- **Status:** 🔵 In review: built, tested and verified through the live API. The UI builds, lints and type-checks but still needs a browser click-through (see Build notes).
- **Phase:** Phase 3 — Academic Operations
- **Completed on:** —
- **Owner agent:** `module-builder`

> Daily student attendance (Present / Absent / Late / Leave) per section, taken in one request per section and day, with edits, a monthly grid, percentages, a per-student report and attendance dashboards for every audience (spec §14).

## Scope

Included:
- **Taking:** daily attendance for a whole section in one request (POST bulk), and editing a day's sheet (re-saving, or PUT by id).
- **Reports:** a monthly grid with per-student percentages, a per-student year report (totals, by month, the days not present), and a list of sheets taken with counts.
- **Dashboards:**
  - Admin: today's sections taken / still to take, absences.
  - Teacher: their sections, with a "take now" link.
  - Parent: each child's attendance %.
  - Student: their own %.
- **The teacher portal gets its first navigation** (Home, Attendance).

**Deferred:** teacher/employee attendance (spec: "use the same concept for teachers and employees"). Its roster (staff) and markers (HR/admin) are different, and there's no staff/HR module yet; add it alongside HR features. Leave-application workflow, device integration and SMS absence alerts stay excluded (spec §36).

## Design decisions

- **One sheet per section per day:** `attendances` with unique (`section_id`,`date`), and one row per student in `attendance_details` with unique (`attendance_id`,`student_id`).
  - Saving the same section and date again **updates** the sheet and rows (idempotent upsert), backed by the unique indexes. Two saves of the same section queue behind a section row lock.
  - Rows point at the student's **enrollment** (`student_enrollment_id`, RESTRICT), so an enrollment with attendance can't be deleted; it changes status instead.
  - Rows carry `student_id` + `date` for the (student_id, date) index spec §54 asks for.
  - The sheet's class/section pair is checked by the same composite FK as enrollments.
- **Who's on the sheet** (the roll for a date): enrollments in that section for the academic year covering the date that were still running that day (active, or ended after it), of students who aren't archived, in roll order.
  - So a student transferred on 15 June appears on 10 June but not 16 June.
  - Every student on the roll must be marked and nobody else; there's no partial save.
  - New sheets start everyone as Present in the UI, so teachers only change the exceptions.
- **Dates:** not in the future, and inside an academic year (years never overlap, Module 06).
- **Two-layer authorization (spec §52)** on every endpoint:
  - The `attendance.*` permission comes from route middleware, plus **`App\Support\TeacherScope`**: a teacher works only with sections they teach a subject in **or** are class teacher of. Any other section gets **403** ("You can only work with the sections you teach.").
  - Re-saving a sheet that's already taken also needs `attendance.edit`.
  - Admin and staff are not section-scoped; their permission decides.
  - `TeacherScope` is now shared: `Student::visibleTo` uses it too.
- **Percentage** = (present + late) ÷ days marked × 100, to 1 decimal, computed server-side (`AttendanceService::summarize`). Late counts as attended, and leave (excused) does not. It's null when nothing was marked.

## Database

- `attendances` — `academic_year_id`, `class_id`, `section_id` (composite FK with class), `date`, `taken_by`, `updated_by` (users), timestamps. Unique (`section_id`,`date`); index (`academic_year_id`,`date`).
- `attendance_details` — `attendance_id` (cascade), `student_enrollment_id` (RESTRICT), `student_id`, `date`, `status` (`App\Enums\AttendanceStatus`), `remark`. Unique (`attendance_id`,`student_id`); index (`student_id`,`date`).

## Permissions

`attendance.view|create|edit` (seeded in Module 02):
- Admin: all.
- Teacher: view, create and edit (scoped to their sections).
- Principal and Receptionist: view.
- Parents and students see only their own figures, through the dashboard.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /attendance/sections | attendance.view | sections the caller may take (teachers: their own) with student count + `marked_today` |
| GET | /attendance/sheet?section_id&date | attendance.view | the day's roll with saved statuses, who took it |
| POST | /attendance/bulk | attendance.create (+ .edit if the sheet exists) | `{section_id, date, records[{student_id, status, remark?}]}`; 201 created / 200 updated |
| PUT | /attendance/{id} | attendance.edit | `{records[]}`; same rules |
| GET | /attendance | attendance.view | sheets newest first with counts; `?section_id&class_id&from&to` |
| GET | /attendance/monthly?section_id&month=YYYY-MM | attendance.view | days × students grid + summary |
| GET | /students/{id}/attendance | attendance.view | a year's totals, months, exceptions; teachers: own students only (404) |

## Backend tasks

- [x] Migrations + models (`Attendance`, `AttendanceDetail`, `AttendanceStatus`), unique + composite indexes. No soft deletes: sheets are corrected, not deleted.
- [x] `SaveAttendanceRequest`; section scope via `TeacherScope` (the doc's `AttendancePolicy`)
- [x] `SaveAttendanceSheet` action (idempotent upsert, whole-roll rule, date checks) + `AttendanceService` (roster, summary, monthly, student report)
- [x] Routes; four dashboard widgets (`attendance_today`, `attendance_tasks`, `child_attendance`, `my_attendance`) replace their placeholders

## Frontend tasks

- [x] Attendance page with "Take attendance" (section + date, P/A/L/Lv per student, mark-all, remarks, who took it) and "Monthly" (grid + %) tabs, at `admin/attendance` and `teacher/attendance` (same component; the API scopes teachers)
- [x] Teacher portal navigation (Home, Attendance); admin nav entry
- [x] Student profile attendance summary; dashboard cards for all four audiences
- [x] Typed `attendanceService` + `types/attendance.ts`
- [ ] Browser click-through (see Build notes)

## Acceptance criteria

- [x] A whole section is marked in a single `POST /attendance/bulk` request
- [x] A teacher marking a non-assigned section is blocked with HTTP 403 (two-layer)
- [x] Duplicate (date, student) is prevented by unique constraint + upsert
- [x] Monthly percentage is computed correctly server-side
- [x] Tests written and passing (12 attendance tests; 208 backend tests total)
- [x] Security review: assignment scope enforced in code on every endpoint, not just by permission; re-saving needs edit permission; whole-roll validation; no client-supplied class

## Notes

Teacher attendance shares the concept but not these tables: build it with staff/HR. Later modules can reuse `AttendanceService::summarize` for report cards (Module 18) and `TeacherScope` for any teacher-facing endpoint.

**Build notes (2026-10-05):**
- **Not yet clicked through in a browser** (in-app browser tools still blocked this session). The screens compile, lint and type-check, and call the endpoints verified below. **To finish:** sign in as `farhana.teacher@school.test`, open Attendance in the teacher portal, take and change a day, check the Monthly tab, then the admin Attendance page and a student profile; mark ✅.
- Verified live through the API on MariaDB (2026-10-05):
  - **Sections:** Farhana's list = 6A (2 students) + 6B (1); the demo teacher's = 6A only.
  - **Taking:** the 6A sheet showed Nusrat (1) and Rafi (2) unmarked. Farhana saved it (Rafi absent, "Fever") → 201. Re-saving with Rafi late ("Bus delayed") → 200, with counts updated, not duplicated. Farhana also took 6B.
  - **Scope:** the demo teacher posting 6B → **403** "You can only work with the sections you teach."
  - **Reports:** the monthly 6A grid shows 5 Oct with Nusrat P / Rafi L, both 100%. Rafi's report lists the late day with its remark.
  - **Dashboards:**
    - Admin: 2 of 2 sections taken today, 100%.
    - Farhana: both sections taken.
    - karim.parent: Nusrat 100%.
- Dev DB: 2026-10-05 sheets for 6A (Nusrat present, Rafi late) and 6B (Anika present), taken by Farhana Akter.
