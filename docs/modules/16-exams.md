# Module 16 — Exams

- **Status:** ⬜ Not started
- **Phase:** Phase 3 — Academic Operations
- **Completed on:** —
- **Owner agent:** `module-builder`

> Exam definitions (monthly, half-yearly, annual, model, test, custom) with per-class subject configuration (full marks, pass marks, exam date) and a configurable grade scale that later marks and results depend on.

## Scope

Includes (spec §16): create exams tied to an academic year and type, configure exam subjects per class-section (full marks, pass marks, exam date), build the exam schedule, and define the grade scale (grade bands with mark ranges and grade points). Provides the reference data that Module 17 (Marks) and Module 18 (Results) read.

Excludes: marks entry (Module 17), result calculation and publishing (Module 18), and admit-card/seat-plan generation (deferred).

## Dependencies

`09 — Subjects`, `08 — Sections`, `06 — Classes`. Reads academic year from School Settings / Academic Years.

## Database (tables owned / referenced)

- `exams` — id, name, type enum(monthly, half_yearly, annual, model, test, custom), academic_year_id (FK), start_date, end_date nullable, timestamps, soft deletes. Index (academic_year_id, type).
- `exam_subjects` — id, exam_id (FK, cascade), class_id (FK), section_id (FK nullable = all sections), subject_id (FK), full_marks decimal(6,2), pass_marks decimal(6,2), exam_date, timestamps. Unique (exam_id, class_id, section_id, subject_id). Index (exam_id, class_id).
- `grades` — id, name (e.g. A+), grade_point decimal(4,2), timestamps.
- `grade_scales` — id, grade_id (FK), min_mark decimal(5,2), max_mark decimal(5,2), academic_year_id (FK nullable), timestamps. Index (grade_id); ranges must not overlap.
- References: `classes`, `sections`, `subjects`, `academic_years`.

## Permissions

Introduces `exam.view`, `exam.create`, `exam.edit`, `exam.delete`. Configuration is admin/exam-controller scoped; teachers get `exam.view` only. Grade-scale edits are admin-restricted (they alter every downstream result).

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /exams | exam.view | paginated; filter year/type |
| POST | /exams | exam.create | create exam header |
| PUT | /exams/{id} | exam.edit | update exam |
| DELETE | /exams/{id} | exam.delete | soft delete |
| POST | /exams/{id}/subjects | exam.edit | configure subject full/pass marks + date |
| GET | /exams/{id}/schedule | exam.view | listable schedule |
| GET/POST | /grade-scales | exam.edit | define grade bands |

## Backend tasks

- [ ] Migrations + models (FKs, enums, unique + composite indexes, soft deletes)
- [ ] Form Requests (`StoreExamRequest`, `StoreExamSubjectRequest`) with `authorize()`
- [ ] `GradeScaleService` validating non-overlapping mark ranges (no logic in controllers)
- [ ] `ExamPolicy` for edit/delete scope
- [ ] `ExamResource` / `ExamSubjectResource` / `GradeResource`
- [ ] Routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] Admin: exam CRUD + per-class subject config grid (full/pass marks, date)
- [ ] Admin: grade-scale editor
- [ ] Schedule list view (per class/section)
- [ ] Typed `examService` in `services/` (exams, subjects, grade scales)

## Acceptance criteria

- [ ] An exam is configured per class with each subject's full and pass marks
- [ ] A grade scale is defined with non-overlapping bands and grade points
- [ ] The exam schedule is listable per class/section
- [ ] pass_marks ≤ full_marks enforced; duplicate exam-subject blocked by unique key
- [ ] Tests written and passing (exam create, subject config, grade-scale overlap guard)
- [ ] Security review passed (grade-scale edits admin-only)

## Notes

`full_marks`/`pass_marks` are decimals to allow half-marks. A null `section_id` on `exam_subjects` means the config applies to all sections of the class. Grade scale is versionable per academic year so historical results keep their original grading.
