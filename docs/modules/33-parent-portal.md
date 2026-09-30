# Module 33 — Parent Portal

- **Status:** ⬜ Not started
- **Phase:** Phase 6 — Portals
- **Completed on:** —
- **Owner agent:** `module-builder`

> A read-heavy portal (spec §20) that lets an authenticated guardian view their own children and, per child, attendance, published results, fees/dues, class routine, homework, notices, and messages — composing existing modules' data behind guardian-scoped `/parent/*` endpoints.

## Scope

Includes (spec §20): list "my children" (a parent may have several, via `student_guardians`), and per-child attendance, results (published only, spec §56), fees/dues, class routine, homework, notices, and messages. Every response is strictly scoped to the authenticated guardian's linked students — this is the "enter once, use everywhere" principle (spec §68): no new academic tables, only guardian-scoped composition of existing ones.

Excludes: writing any academic record (read-only over children's data), fee payment (Module 20), and result computation. Homework and messages are two-way communication surfaces owned by the Teacher Portal (Module 34); this portal only reads them (or introduces the tables if built first — see Database).

## Dependencies

`10 — Guardians` (the `student_guardians` link that defines a parent's children), `11 — Students`, `14 — Attendance`, `18 — Results`, `19 — Fees`, `25 — Notices`. Reads Routine (Module 15) and, if present, `homework`/`messages` from Module 34.

## Database (tables owned / referenced)

- Owns no new academic tables. If the Teacher Portal (Module 34) has not yet delivered them, introduces `homework` (id, class_id FK, section_id FK, subject_id FK, teacher_id FK, title, description, attachment nullable, due_date, timestamps) and `messages` (id, sender_id FK users, recipient_id FK users, student_id nullable FK, body, read_at nullable, timestamps); otherwise references them read-only.
- References: `student_guardians`, `students`, `attendances`/`attendance_details`, `marks`/results (published flag), `fee_invoices`/`fee_invoice_items`, routine tables, `notices`, `homework`, `messages`.

## Permissions

Introduces `parent.portal` (role Parent). Beyond the permission, a **two-layer** scope rule (spec §52) applies: a `GuardianScope` resolves the authenticated user → `student_guardians` → allowed `student_id`s, and every query is constrained to that set. View permissions are reused but ownership-gated (a guardian never sees another family's records).

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /parent/children | parent.portal | the guardian's linked students only |
| GET | /parent/children/{studentId}/attendance | parent.portal | 403 if child not linked; paginated |
| GET | /parent/children/{studentId}/results | parent.portal | published results only |
| GET | /parent/children/{studentId}/fees | parent.portal | invoices, dues (read-only) |
| GET | /parent/children/{studentId}/routine | parent.portal | class routine |
| GET | /parent/children/{studentId}/homework | parent.portal | by child's class/section |
| GET | /parent/notices | parent.portal | notices visible to parents |
| GET | /parent/messages | parent.portal | threads addressed to the guardian |

## Backend tasks

- [ ] `GuardianScope` service/trait resolving authenticated guardian → allowed student ids
- [ ] Form Requests with `authorize()` re-checking child ownership on every `{studentId}`
- [ ] Policies: `viewChild`, `viewResult` (published only), `viewInvoice` scoped to guardian
- [ ] Read services composing attendance, results, fees, routine, homework, notices per child
- [ ] API Resources shaping child-scoped responses (no cross-family leakage)
- [ ] Routes under `/api/parent` with JWT + `permission:parent.portal` middleware

## Frontend tasks

- [ ] Pages under Next.js `parent/` area (dashboard, children switcher, per-child tabs)
- [ ] Child selector so a multi-child guardian switches context; per-child views
- [ ] Typed `parentService` in `services/` (children, attendance, results, fees, routine, homework, notices, messages)
- [ ] Role-appropriate visibility; results tab shows only published results

## Acceptance criteria

- [ ] A guardian sees only their own children's data; another child returns HTTP 403 (or empty)
- [ ] Results are shown only when published (draft/unpublished never exposed)
- [ ] A guardian with multiple children can switch between them
- [ ] No parent endpoint exposes another student's attendance/fees/results
- [ ] Tests written and passing (scope 403, multi-child switch, published-only results)
- [ ] Security review passed (two-layer auth: `parent.portal` + guardian ownership scope)

## Notes

The two-layer rule is the crux: permission grants entry, but `GuardianScope` decides which records are returned (spec §52, §68). `homework`/`messages` ownership is settled with the Teacher Portal (Module 34) to avoid duplicate tables. Notices are cacheable per spec §60.
