# Module 17 — Marks Entry

- **Status:** ✅ Complete
- **Phase:** Phase 3 — Academic Operations
- **Completed on:** 2026-10-20
- **Owner agent:** `module-builder`

> Teacher marks entry (CQ / MCQ / practical) per exam-subject, with server-computed totals validated against full marks; marks are stored as DRAFT and never exposed publicly until results are published.

## Scope

Includes (spec §16 marks, §52 authorization): enter marks per student for a configured exam-subject, bulk-enter marks for a whole class-subject in one request, server-side total computation, and validation of each component and the total against the exam-subject full marks. Marks begin in DRAFT state (spec §56) and stay internal.

Excludes: grade/GPA/position calculation and publishing (Module 18) and public result display (Module 32 — Public Result). Exposure of marks is explicitly out of scope here.

## Dependencies

`16 — Exams` (exam-subject + full/pass marks), `12 — Teachers` (assigned-subject scope + `entered_by`), `13 — Enrollments` (which students belong to the class-subject).

## Database (tables owned / referenced)

- `marks`:
  - Columns: id, exam_subject_id, student_id, student_enrollment_id, cq / mcq / practical decimal(6,2) nullable, total decimal(6,2) nullable, absent bool, status (`draft` | `submitted`, `App\Enums\MarkStatus`), entered_by, updated_by, submitted_at, submitted_by, timestamps.
  - `total` is computed server-side and is NULL when the student is absent.
  - Decimal(6,2), not (5,2), to match `exam_subjects` full marks (up to 1000).
  - Foreign keys (all RESTRICT): `exam_subject_id` (a paper with marks can't be removed from the exam), `student_id`, `student_enrollment_id` (the enrollment the student sat the paper in).
  - Users: FKs set NULL on delete.
  - Unique (exam_subject_id, student_id). Indexes (student_id), (exam_subject_id, status).
- A paper without a CQ/MCQ/practical split is marked as one figure, stored as `total` with no parts.
- References: `exam_subjects`, `student_enrollments`, `teaching_assignments`, `users`.

## Permissions

Uses `exam.marks_entry` (already in the catalogue: Admin, Teacher). Reopening a submitted sheet needs `exam.edit` (Admin by default).

**Two-layer authorization (spec §52):**
- The permission gates the route.
- Then `TeacherScope::authorizeSubject()` requires the teacher's own `teaching_assignments` row for that class subject in that section (403).
- Being class teacher of the section is not enough.
- The scope fails closed: only exam admins (`exam.edit`) are unscoped (`TeacherScope::subjectScoped`). Any other role given `exam.marks_entry` still needs a linked teacher record and the matching assignment, and a teacher who also holds Principal stays scoped.
- Teaching assignments carry no year, so scoped teachers work only on the **current year's** exams (403 otherwise); older papers belong to exam admins.
- The doc's `MarksPolicy` is this helper, in line with the existing `TeacherScope` pattern.

## API endpoints

A **sheet** is one paper (`exam_subject_id`) as sat by one section (`section_id`). A class-wide paper has one sheet per section.

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /exams/{id}/marks | exam.marks_entry | the caller's sheets with progress; paginated, `?class_id`; teachers: own subject + section pairs only |
| GET | /exams/{id}/marks/sheet | exam.marks_entry | `?exam_subject_id&section_id`: the roll with marks, parts, opens_on, status, can_edit/can_reopen |
| POST | /exams/{id}/marks/bulk | exam.marks_entry | save a whole sheet as drafts (blank row = clear); errors `records.N.field` |
| POST | /exams/{id}/marks/submit | exam.marks_entry | lock the sheet once every student has marks or is absent |
| POST | /exams/{id}/marks/reopen | exam.edit | submitted → draft for corrections |
| PUT | /marks/{id} | exam.marks_entry | edit one draft mark (scoped) |

## Backend tasks

- [x] Migration + `Mark` model + `MarkStatus` enum (FKs, unique, indexes)
- [x] Form Requests (`MarkSheetRequest`, `SaveMarksRequest`, `UpdateMarkRequest`): shapes only
- [x] `MarksService::resolve()`:
  - The paper must belong to the exam (404).
  - The section must sit the paper (422).
  - The teacher must hold the subject (403).
- [x] `EnterMarks` action:
  - Computes the total server-side from the parts; a client `total` is never read.
  - Each part ≤ its cap and the total ≤ full marks.
  - Every part of a split paper must be entered, or the row left blank.
  - Absent rows can't carry marks.
  - Students must be on the paper's roll.
  - Locked when submitted (409).
  - Closed before the paper's date (422).
- [x] `SubmitMarks` (submit / reopen, idempotent; the paper row is locked against races)
- [x] `MarkResource` (internal only; no public serializer)
- [x] Routes under `/api` with JWT + `permission:` middleware
- [x] `SaveExamSubjects`: a paper's full marks and split are fixed once it has marks (pass marks and dates can still change)
- [x] Real `pending_marks` teacher widget replaces its placeholder
- [x] `App\Support\SchoolClock`: "today" is the school's day (Settings timezone, default Asia/Dhaka), not UTC. Used by attendance, exams, enrollments, admissions, certificates and the marks opening day.

## Frontend tasks

- [x] `admin/marks` and `teacher/marks`:
  - Exam picker, defaulting to the latest exam that has started.
  - Class filter.
  - Sheets with paper date, entered/students bar, status badge, and Enter marks / View / "Opens {date}".
- [x] `…/marks/sheet` grid:
  - Columns: roll, student, then one input per part capped at its maximum (red when over), or a single Marks column.
  - Live total, red below the pass marks.
  - Absent checkbox.
  - Enter / ↑ / ↓ move down and up the column.
  - Per-row errors and an unsaved-changes hint.
  - Save draft; Save & submit (with a confirm).
  - Reopen for exam admins.
  - Locked and not-open-yet banners.
- [x] Client-side max-value hints mirroring server validation
- [x] Typed `marksService` in `services/` (list, sheet, bulk, submit, reopen, edit)
- [x] Only assigned subjects selectable (the list is server-scoped)
- [x] Nav entries ("Marks entry", admin + teacher portal); dashboard "Marks to enter" card for teachers

## Acceptance criteria

- [x] A teacher can enter marks only for an assigned subject; any other exam-subject returns HTTP 403
- [x] A mark component or total exceeding full_marks is rejected (422)
- [x] `total` is computed server-side and ignores any client-supplied total
- [x] Marks remain in DRAFT and are not exposed publicly until Module 18 publishes results
- [x] Bulk entry covers a whole class-subject in one request
- [x] Tests written and passing:
  - 11 marks tests: 403 scope, over-max guard, server total, submit/reopen lock, fixed split, opening day in school time, widget, draft invisibility, fail-closed scope, current-year limit, audit trail, section-move guard.
- [x] Security review passed (two-layer ownership, no draft leakage, no client-trusted totals). No critical/high findings; fixed:
  - M1: the subject scope keyed off the Teacher role and failed open for other roles → now fails closed (`exam.edit` = unscoped).
  - M2: a current teacher could rewrite older years' drafts → scoped teachers are limited to the current year.
  - L1: caps were checked against a paper read before the lock → saves re-read the paper under `FOR UPDATE`; `SaveExamSubjects` locks its papers too.
  - L2: moving an enrollment dragged its marks to another sheet → a student with marks can't change section; single edits are locked when anything on the sheet is submitted.
  - L3: reopening erased who submitted → changes to entered marks, clears, submits and reopens are written to `audit_logs` (`App\Support\Audit`).
  - L4: `Mark` was mass-assignable → `$fillable` is the computed values only; links and actors are force-filled; the controller passes validated records.

## Notes

Two-layer auth is the load-bearing control here: permission gates the route, the policy gates the specific exam-subject. Draft vs submitted is an internal handoff to Module 18; neither state is public. Component caps (e.g. MCQ max) can be modeled per exam-subject later.

**Build notes (2026-10-20):**
- **Who's on a sheet:** the exam year's roll on the paper's day (`StudentEnrollment::onRoll`, shared with attendance).
  - Students who left before the paper aren't listed.
  - Marks of students no longer on the roll stay visible and editable.
- **When marks open:** marks can be entered from the paper's date (or the exam's start, if the paper is undated), in the school's timezone.
- **For Results (18):**
  - Calculate from `submitted` marks.
  - Block reopening a sheet once results built on it are approved/published.
  - Absent = fail.
  - Per-part pass marks are still not modelled.
- **Not clicked through in the browser by Claude:** the user runs the dev servers. Checked instead:
  - Feature tests.
  - Larastan (0 errors).
  - ESLint and `tsc`.
  - The list, sheet and class-filter queries against the dev MariaDB.
