# Module 13b — Student ID cards, certificates & import/export

- **Status:** ⬜ Not started
- **Phase:** Phase 2 — Academic Core
- **Completed on:** —
- **Owner agent:** `module-builder`

> Output and bulk tools built on student + enrollment data. Split out of Module 13 (originally carried there from Module 11) because each is a self-contained feature that needs finished enrollments.

## Scope

- **Student ID cards** for a student or a whole section: photo (private, via signed link), name, student ID, class/section/roll, academic year, school name/logo, and validity.
- **Certificates:**
  - The **transfer certificate (TC)**, from an enrollment with status `transferred`: date, exit reason/destination and the student's details.
  - The **testimonial / character certificate**, which needs results (Module 18), so build it here only if results exist; otherwise leave a hook.
- **Import students** from CSV/Excel:
  - Student fields (typed-in `student_id` / `admission_no` are supported for migrated records) plus optional class/section/roll for the current year, so import enrolls in the same pass.
  - Validate every row first, report errors per row, and commit all or nothing.
- **Export students** (CSV/Excel; PDF lists optional) with filters (year, class, section, status), including class/section/roll.

## Open decisions (settle when starting)

- **Output format:**
  - (a) **Print-ready HTML pages** (ID cards and TC laid out for A4/card size; the user prints or saves as PDF from the browser). No new dependency, and fine for a single school.
  - (b) **Server-side PDFs** via a library such as `barryvdh/laravel-dompdf`, generated as queued jobs (spec §61) with a job-status UI.
  - Recommendation: (a) for ID cards and TC now, and (b) only if bulk/emailed PDFs are needed.
- **Queues:** spec §61 lists student import and report export as queued work. A school-sized import (a few thousand rows) can run synchronously within limits. A queued job needs `php artisan queue:work` running in production, plus an import/export job table for status and downloads.

## Dependencies

- `11 — Students`, `13 — Enrollments`, `04 — School Settings` (school name/logo on cards and certificates).

## API endpoints (proposed)

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /students/{id}/id-card | student.view | card data or PDF |
| GET | /sections/{id}/id-cards?academic_year_id= | student.view | a section's cards |
| GET | /enrollments/{id}/transfer-certificate | enrollment.view | only for transferred enrollments |
| POST | /students/import | student.create + enrollment.create | validate → commit; per-row errors |
| GET | /students/export | student.export | filters as the list; CSV/Excel |

## Acceptance criteria

- [ ] ID cards show the photo, IDs and current class/section/roll, and print cleanly (single and per section)
- [ ] A TC can be produced only for a transferred enrollment, with its date and reason
- [ ] Import validates every row and reports errors per row; nothing is saved when any row fails; enrollments are created for rows with class/section
- [ ] Export honours the filters and includes class/section/roll; `student.export` is required
- [ ] Teacher scoping (`Student::visibleTo`) applies to cards/exports for teacher callers
- [ ] Tests written and passing; security review (no PII beyond what the caller can already see; uploads validated)
