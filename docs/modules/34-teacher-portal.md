# Module 34 — Teacher Portal

- **Status:** ⬜ Not started
- **Phase:** Phase 6 — Portals
- **Completed on:** —
- **Owner agent:** `module-builder`

> A scoped work surface (spec §22) where a teacher views their routine and assigned classes/students, takes attendance, enters marks, uploads homework, reads notices, sends messages, and reviews attendance history — every action constrained to the teacher's assigned classes/subjects, with no admin access.

## Scope

Includes (spec §22): view routine, view assigned classes and their students, take attendance, enter marks, upload homework, view notices, send messages, and view attendance history. All actions are limited to **assigned** classes/subjects via two-layer authorization (spec §52). Attendance and marks reuse Modules 14 and 17 with an assignment scope rather than reimplementing them. This portal owns the `homework` and `messages` tables consumed read-only by the Parent (33) and Student (35) portals.

Excludes: any admin capability — a teacher must not reach admin endpoints, manage other teachers, publish results, or edit fees (spec §22). Result publishing stays with admin/principal (spec §56).

## Dependencies

`12 — Teachers` (identity + assignment), `13 — Enrollments` (roster of assigned sections), `14 — Attendance`, `15 — Routine`, `17 — Marks`, `25 — Notices`. Assignment source is `class_subjects.teacher_id` and class-teacher links.

## Database (tables owned / referenced)

- `homework` — id, class_id (FK), section_id (FK), subject_id (FK), teacher_id (FK), title, description, attachment (nullable, validated), due_date, timestamps. Indexes (class_id, section_id), (teacher_id).
- `messages` — id, sender_id (FK users), recipient_id (FK users), student_id (nullable FK), body, read_at (nullable), timestamps. Index (recipient_id, read_at).
- References: `teachers`, `class_subjects` (teacher assignment), `student_enrollments`, `attendances`/`attendance_details`, `marks`/`exam_subjects`, routine tables, `notices`.

## Permissions

Introduces `teacher.portal`, plus reuses `attendance.create`, `attendance.edit`, `exam.marks_entry`, and adds `homework.create`, `message.send`. Beyond the permission, an `AssignmentScope` (spec §52) confirms the teacher owns the target class/section/subject/exam before any write. No admin permissions are granted to the Teacher role.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /teacher/routine | teacher.portal | the teacher's own routine |
| GET | /teacher/classes | teacher.portal | assigned classes/sections only |
| GET | /teacher/classes/{id}/students | teacher.portal | roster of an assigned section |
| POST | /teacher/attendance | attendance.create | scoped to assigned section (reuses M14) |
| POST | /teacher/marks | exam.marks_entry | scoped to assigned subject/exam (reuses M17) |
| POST | /teacher/homework | homework.create | upload with validated attachment |
| GET | /teacher/notices | teacher.portal | notices for teachers |
| POST | /teacher/messages | message.send | to linked guardians/students |
| GET | /teacher/attendance/history | teacher.portal | past sheets for assigned sections |

## Backend tasks

- [ ] `AssignmentScope` service resolving teacher → assigned class/section/subject ids
- [ ] Form Requests with `authorize()` verifying assignment on every write (attendance, marks, homework)
- [ ] Policies rejecting non-assigned targets with 403; deny all admin actions
- [ ] Homework upload validation: MIME + max size (spec §51)
- [ ] Reuse Module 14/17 actions with the scope injected; API Resources for responses
- [ ] Routes under `/api/teacher` with JWT + `permission:teacher.portal`; admin routes excluded

## Frontend tasks

- [ ] Pages under Next.js `teacher/` area (dashboard, routine, classes, attendance, marks, homework, messages)
- [ ] Assigned-only lists (no global class/student browsing); attendance + marks forms
- [ ] Homework upload form with file validation and error states
- [ ] Typed `teacherService` in `services/`; no links to admin areas

## Acceptance criteria

- [ ] A teacher can act only on assigned classes/subjects; others return HTTP 403 (two-layer)
- [ ] A teacher cannot reach any admin endpoint or publish results
- [ ] Homework upload is validated (MIME, size) and rejected otherwise
- [ ] Attendance and marks reuse Modules 14/17 with the assignment scope enforced
- [ ] Tests written and passing (assignment 403, admin-denied, upload validation, scoped writes)
- [ ] Security review passed (two-layer auth: permission + assignment; no admin leakage)

## Notes

Permission alone is insufficient (spec §52): `AssignmentScope` decides which class/subject a teacher may write. This portal is the canonical owner of `homework`/`messages`; portals 33 and 35 read them. Result publishing is deliberately out of scope (spec §56).
