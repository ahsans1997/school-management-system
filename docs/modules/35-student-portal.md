# Module 35 — Student Portal

- **Status:** ⬜ Not started
- **Phase:** Phase 6 — Portals
- **Completed on:** —
- **Owner agent:** `module-builder`

> A read-only portal (spec §21) where an authenticated student views their own profile, attendance, published results, routine, fees, notices, events, homework, and downloads — composing existing modules' data behind student-scoped `/student/*` endpoints, with no write access to academic records.

## Scope

Includes (spec §21): the student's own profile, attendance, results (published only, spec §56), class routine, fees, notices, events, homework, and downloads. Every response is strictly scoped to the authenticated student (spec §68, "enter once, use everywhere"): no new academic tables, only read composition of existing ones. All access is read-only.

Excludes: any write to academic records (attendance, marks, results, fees), fee payment, and homework submission (deferred). Results computation and publishing remain with Exams/Results and admin (spec §56).

## Dependencies

`11 — Students` (own profile/identity), `14 — Attendance`, `18 — Results`, `15 — Routine`, `19 — Fees`, `25 — Notices`, `26 — Events`, `29 — Downloads`. Reads `homework` from the Teacher Portal (Module 34).

## Database (tables owned / referenced)

- Owns no new tables — read-only over existing module data.
- References: `students` (own row), `student_enrollments`, `attendances`/`attendance_details`, `marks`/results (published flag), routine tables, `fee_invoices`/`fee_invoice_items`, `notices`, `events`, `homework`, `downloads`.

## Permissions

Introduces `student.portal` (role Student). Beyond the permission, a **two-layer** scope rule (spec §52): a `StudentScope` resolves the authenticated user → their single `student_id`, and every query is constrained to that record. View permissions are reused but self-scoped; no create/update/delete permissions on academic data are granted.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /student/profile | student.portal | own student record only |
| GET | /student/attendance | student.portal | own attendance + percentage |
| GET | /student/results | student.portal | published results only |
| GET | /student/routine | student.portal | own class routine |
| GET | /student/fees | student.portal | own invoices/dues (read-only) |
| GET | /student/notices | student.portal | notices visible to students |
| GET | /student/events | student.portal | upcoming events |
| GET | /student/homework | student.portal | for the student's class/section |
| GET | /student/downloads | student.portal | published download files |

## Backend tasks

- [ ] `StudentScope` service/trait resolving authenticated user → own `student_id`
- [ ] Form Requests / policies enforcing self-ownership; deny all academic writes
- [ ] `viewResult` policy returning published results only
- [ ] Read services composing profile, attendance, results, routine, fees, notices, events, homework, downloads
- [ ] API Resources shaping self-scoped responses (no other student's data)
- [ ] Routes under `/api/student` with JWT + `permission:student.portal`; GET-only

## Frontend tasks

- [ ] Pages under Next.js `student/` area (dashboard + read-only tabs)
- [ ] Profile view mirroring the student record; attendance %, results, routine, fees
- [ ] Typed `studentService` in `services/` (profile, attendance, results, routine, fees, notices, events, homework, downloads)
- [ ] Read-only UI (no academic write controls); results tab shows published only

## Acceptance criteria

- [ ] A student sees only their own data; another student's records are never returned
- [ ] Results are shown only when published (draft/unpublished never exposed)
- [ ] No write access to academic records (attendance, marks, results, fees) from this portal
- [ ] The profile view matches the authenticated student's record
- [ ] Tests written and passing (self-scope, published-only results, read-only enforcement)
- [ ] Security review passed (two-layer auth: `student.portal` + self-ownership scope)

## Notes

Self-scoping is enforced server-side via `StudentScope`, not by trusting any client-supplied id (spec §51, §52). All endpoints are GET; the portal composes existing modules and adds no tables. Notices/events/downloads are cacheable (spec §60).
