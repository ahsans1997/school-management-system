# Module 18 — Results

- **Status:** ⬜ Not started
- **Phase:** Phase 3 — Academic Operations
- **Completed on:** —
- **Owner agent:** `module-builder`

> Computes results from entered marks (total, grade, GPA, position/merit), produces mark sheets, report cards, tabulation sheets and statistics, and moves them through a staged draft → review → approved → published → public workflow with auditing.

## Scope

Includes (spec §17, §56): calculate per-student results from `marks` (subject grades, total, GPA, overall grade, class position/merit), generate report card, mark sheet, tabulation sheet and result statistics, and run the staged publishing workflow — draft → review → approved → published — before any public exposure. Publishing is audited.

Excludes: the public-facing lookup endpoint and student-data minimization for it (Module 32 — Public Result); marks entry (Module 17). Only published results are handed to the public module.

## Dependencies

`17 — Marks Entry` (source marks), `16 — Exams` (grade scale, full/pass marks, exam-subject map). Reads enrollment for class position grouping.

## Database (tables owned / referenced)

- `results` — id, exam_id (FK), student_id (FK), class_id (FK), section_id (FK), gpa decimal(4,2), grade_id (FK), total_marks decimal(8,2), position int nullable, status enum(draft, review, approved, published) default draft, published_at nullable, published_by (FK users) nullable, timestamps. Unique (exam_id, student_id). Indexes (exam_id, class_id, section_id), (status).
- `result_details` *(computed per subject)* — id, result_id (FK, cascade), exam_subject_id (FK), obtained decimal(6,2), grade_id (FK), passed bool. Index (result_id).
- References: `marks`, `exam_subjects`, `grades`/`grade_scales`, `student_enrollments`. Publish actions write to `audit_logs` (spec §43).

## Permissions

Introduces `result.view`, `result.publish`. Two-layer authorization (spec §52): `result.publish` is restricted to principal/exam-controller; computing/reviewing may be broader but scoped to class-section. Only status transitions by authorized roles advance the workflow.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| POST | /exams/{id}/results/calculate | result.view | compute from marks (draft) |
| GET | /exams/{id}/results | result.view | paginated; internal, all statuses |
| GET | /results/{id}/report-card | result.view | report card |
| GET | /exams/{id}/tabulation | result.view | tabulation sheet |
| GET | /exams/{id}/statistics | result.view | pass %, grade distribution |
| POST | /results/{id}/publish | result.publish | draft→...→published; audited |

## Backend tasks

- [ ] Migrations + models (FKs, unique, status enum, indexes on status + grouping)
- [ ] `CalculateResultAction` / `ResultService` — grade lookup from grade scale, GPA, class position/merit; server-side only (no logic in controllers)
- [ ] `ResultPolicy` + Form Requests enforcing status transitions and publish scope
- [ ] `PublishResultAction` — atomic transition + `audit_logs` entry (who/when)
- [ ] `ReportCardResource` / `TabulationResource` / `MarkSheetResource`
- [ ] Routes under `/api` with JWT + `permission:` middleware; draft results never serialized to public

## Frontend tasks

- [ ] Admin: result calculation trigger + review/approve/publish workflow UI
- [ ] Report card, mark sheet, tabulation sheet views (printable)
- [ ] Result statistics dashboard
- [ ] Typed `resultService` in `services/`; publish confirmation with audit note

## Acceptance criteria

- [ ] Results are computed from marks with correct grade, GPA and class position
- [ ] Draft/review/approved results are not publicly visible
- [ ] Only `published` results are exposed to the public endpoint (Module 32)
- [ ] The publish action is recorded in `audit_logs` (user, timestamp, exam)
- [ ] Position/merit ordering is correct within class-section
- [ ] Tests written and passing (calculation, grade/GPA/position, publish gate, draft invisibility, audit)
- [ ] Security review passed (staged workflow enforced, publish scoped, no draft leakage)

## Notes

Position is computed within a class-section grouping over total marks with a defined tie rule. GPA/grade come from the exam's grade scale (Module 16) so historical exams keep their original grading. Recalculation is only permitted while status is draft; published results are immutable except by a re-publish that is itself audited.
