# Module 36 — Reports

- **Status:** ⬜ Not started
- **Phase:** Phase 7 — Reporting, Communication & Audit
- **Completed on:** —
- **Owner agent:** `module-builder`

> Exportable, filter-driven reports (spec §42) that read across the academic and finance modules — each report renders on screen (paginated) and exports to PDF / Excel / CSV, with large exports pushed to a queue (spec §61) and delivered by notification when ready.

## Scope

Includes (spec §42): the V1 report catalogue — Student, Attendance, Teacher, Admission, Fee, Payment, Due, Expense, Income, Exam, Result — each with an on-screen paginated view and an export in PDF, Excel, and CSV. Every report accepts filters (date range, class, section, academic year, plus report-specific ones). Small exports stream inline; large exports are dispatched to a queued job (spec §61) that builds the file, stores it, and fires a "report ready" notification with a download link (Module 37).

Excludes: Library, Transport, and Payroll reports (V2, spec §66); dashboard KPI widgets (Module 05); raw analytics/BI. This module only reads source data — it never mutates it.

## Dependencies

`11 — Students`, `12 — Teachers`, `14 — Attendance`, `18 — Results`, `19 — Fees`, `20 — Payments`, `21 — Expenses` (source data). Reads only; produces no writes into those modules.

## Database (tables owned / referenced)

- `report_exports` — id, user_id (FK, requester), type (enum: students/attendance/teachers/admissions/fees/payments/dues/expenses/income/exams/results), format (enum: pdf/excel/csv), filters (json), status (enum: queued/processing/ready/failed), file_path (nullable), file_disk, rows_count (nullable), error (nullable), expires_at (nullable), timestamps. Index (user_id, status), (type, created_at). Tracks queued exports so they are downloadable and notifiable.
- References (read-only): `students`, `teachers`, `attendances`/`attendance_details`, `marks`/`results`, `fee_invoices`, `payments`, `expenses`, `income`, `admissions`, `exams`.

## Permissions

Per-report Spatie permissions, scoped by role (spec §52): `report.students`, `report.attendance`, `report.teachers`, `report.admissions`, `report.fees`, `report.payments`, `report.dues`, `report.expenses`, `report.income`, `report.exams`, `report.results`. Two-layer authorization — the permission gates the route, and a policy scopes the export owner to their own `report_exports` rows for download. Financial reports (fees/payments/dues/expenses/income) are granted only to accountant/admin/principal.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /reports/students | report.students | paginated on-screen view; filters (class, section, year) |
| GET | /reports/attendance | report.attendance | paginated; date range, class, section |
| GET | /reports/fees | report.fees | paginated; class, year, status |
| GET | /reports/payments | report.payments | paginated; date range, method |
| GET | /reports/{report} | report.{report} | same pattern: dues/teachers/admissions/expenses/income/exams/results |
| POST | /reports/{report}/export | report.{report} | format=pdf\|excel\|csv; large → queued job, returns export id |
| GET | /reports/exports | report.* | paginated list of caller's exports |
| GET | /reports/exports/{id} | report.* (owner) | status; download link when `ready` |

## Backend tasks

- [ ] Migration + `ReportExport` model (enums, indexes)
- [ ] Form Requests per report validating filters + `authorize()` (permission check)
- [ ] Policy scoping export download to the requesting user
- [ ] `ReportQueryService` per type (filtered, eager-loaded, paginated) — no logic in controllers
- [ ] Exporters (PDF via a PDF lib, Excel/CSV via Laravel Excel) behind a common `Exporter` contract
- [ ] `GenerateReportExportJob` (queued, spec §61): build file → store → mark `ready` → dispatch notification
- [ ] API Resources for on-screen rows and export status
- [ ] Routes under `/api` with JWT + per-report `permission:` middleware; download served via signed/temporary URL

## Frontend tasks

- [ ] Admin pages under `admin/reports/` — one screen per report with a filter bar (date range, class, section, year) and paginated table
- [ ] Export buttons (PDF / Excel / CSV) with async job-status polling and "ready to download" state
- [ ] Typed `reportService` in `services/` (per-report fetch, export request, export status/download)
- [ ] Role-appropriate visibility — hide reports the user lacks `report.*` for

## Acceptance criteria

- [ ] Each report type generates with correctly filtered data (date range, class, section, year)
- [ ] Export is produced in PDF, Excel, and CSV formats
- [ ] A large export is queued (spec §61), then downloadable, and notifies the requester when ready
- [ ] Each report is permission-gated per report (`report.<type>`), scoped per role
- [ ] Report numbers reconcile with the source modules (e.g. fee/payment/due totals match Modules 19/20)
- [ ] On-screen views are paginated (`?page&per_page`)
- [ ] Tests written and passing (filtering, per-format export, queued lifecycle, permission gating)
- [ ] Security review passed (two-layer auth, download scoped to owner, financial reports role-restricted)

## Notes

Reports are read-only projections; totals must reconcile with the owning modules rather than recompute money independently (money stays `decimal(15,2)`, spec §53). "Large" is threshold-based (row count) — above it, always queue. Export files expire (`expires_at`) and are pruned by the scheduler. Library/Transport/Payroll reports are deferred to V2 (spec §66).
