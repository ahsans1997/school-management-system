# Module 17 — Marks Entry

- **Status:** ⬜ Not started
- **Phase:** Phase 3 — Academic Operations
- **Completed on:** —
- **Owner agent:** `module-builder`

> Teacher marks entry (CQ / MCQ / practical) per exam-subject, with server-computed totals validated against full marks; marks are stored as DRAFT and never exposed publicly until results are published.

## Scope

Includes (spec §16 marks, §52 authorization): enter marks per student for a configured exam-subject, bulk-enter marks for a whole class-subject in one request, server-side total computation, and validation of each component and the total against the exam-subject full marks. Marks begin in DRAFT state (spec §56) and stay internal.

Excludes: grade/GPA/position calculation and publishing (Module 18) and public result display (Module 32 — Public Result). Exposure of marks is explicitly out of scope here.

## Dependencies

`16 — Exams` (exam-subject + full/pass marks), `12 — Teachers` (assigned-subject scope + `entered_by`), `13 — Enrollments` (which students belong to the class-subject).

## Database (tables owned / referenced)

- `marks` — id, exam_subject_id (FK), student_id (FK), cq decimal(5,2) nullable, mcq decimal(5,2) nullable, practical decimal(5,2) nullable, total decimal(6,2) computed server-side, status enum(draft, submitted) default draft, entered_by (FK users), timestamps. Unique (exam_subject_id, student_id). Indexes (exam_subject_id), (student_id).
- References: `exam_subjects`, `student_enrollments`, `teachers`, `users`.

## Permissions

Introduces `exam.marks_entry`. Two-layer authorization is critical (spec §52): the permission alone is insufficient — a teacher may enter marks ONLY for exam-subjects that map to their assigned subject/class and an authorized exam. Ownership is checked in a policy against the teacher's subject assignments; admin/exam-controller bypass scope.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /exams/{id}/marks | exam.marks_entry | paginated; scoped to assigned exam-subjects |
| POST | /exams/{id}/marks/bulk | exam.marks_entry | bulk entry per class-subject (scoped) |
| PUT | /marks/{id} | exam.marks_entry | edit one mark (scoped, draft only) |

## Backend tasks

- [ ] Migration + model (FKs, unique, indexes)
- [ ] Form Requests (`BulkMarksRequest`) — `authorize()` verifies teacher owns the exam-subject; validate each component ≤ its cap and total ≤ full_marks
- [ ] `MarksPolicy` — record-level check that `exam_subject_id` is in the teacher's assigned subjects/authorized exams (two-layer)
- [ ] `EnterMarksAction` — computes `total` server-side, never trusts client total (spec §51); no logic in controllers
- [ ] `MarkResource` (internal only; no public serializer)
- [ ] Routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] Teacher: marks-entry grid for an assigned exam-subject roster (CQ/MCQ/practical inputs)
- [ ] Client-side max-value hints mirroring server validation
- [ ] Typed `marksService` in `services/` (list, bulk, edit)
- [ ] Only assigned subjects selectable

## Acceptance criteria

- [ ] A teacher can enter marks only for an assigned subject; any other exam-subject returns HTTP 403
- [ ] A mark component or total exceeding full_marks is rejected (422)
- [ ] `total` is computed server-side and ignores any client-supplied total
- [ ] Marks remain in DRAFT and are not exposed publicly until Module 18 publishes results
- [ ] Bulk entry covers a whole class-subject in one request
- [ ] Tests written and passing (403 scope, over-max guard, server total, draft invisibility)
- [ ] Security review passed (two-layer ownership, no draft leakage, no client-trusted totals)

## Notes

Two-layer auth is the load-bearing control here: permission gates the route, the policy gates the specific exam-subject. Draft vs submitted is an internal handoff to Module 18; neither state is public. Component caps (e.g. MCQ max) can be modeled per exam-subject later.
