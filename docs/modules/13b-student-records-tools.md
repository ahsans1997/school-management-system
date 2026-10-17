# Module 13b — Student ID cards, certificates & import/export

- **Status:** ✅ Complete
- **Phase:** Phase 2 — Academic Core
- **Completed on:** 2026-10-06
- **Owner agent:** `module-builder`

> Output and bulk tools built on student + enrollment data. Split out of Module 13 (originally carried there from Module 11) because each is a self-contained feature that needs finished enrollments.

## Scope

- **Student ID cards** for one student or a whole section. Each shows:
  - the photo (private, via signed link), name, student ID, class/section/roll and blood group;
  - an emergency contact (the primary guardian's phone, else the student's own);
  - the school name/logo and the academic year, valid until the year's end.
- **Transfer certificate (TC)**, from an enrollment with status `transferred`. It shows a serial number, the issue date, the student's details, class/section/roll, the leaving date and reason, and a conduct remark.
- **Import students** from CSV or Excel (.xlsx):
  - Student fields, including typed-in `student_id` / `admission_no` for migrated records.
  - Optional class/section/roll, which enrolls the student for the current year in the same pass.
  - Every row is validated first and errors are reported per row; it commits all or nothing.
- **Export students** as CSV or Excel. Filters: year, class, section, enrollment status (or "not enrolled"), archived, and search. Each row includes class/section/roll, status and the primary guardian.

Left out:
- **Testimonial / character certificate:** needs results (Module 18). The `certificates` table and `CertificateType::Testimonial` are the hook.
- **Server-side PDFs and queued jobs:** see Decisions.

## Decisions (settled 2026-10-06)

- **Output format:** print-ready HTML pages (the doc's option a).
  - ID cards print at the standard card size (85.6 × 54 mm), two per row on A4.
  - The TC prints on A4.
  - The browser prints or saves as PDF. When printing, the app chrome is hidden (`print:hidden` on AppShell) and the page is forced light.
  - Server-side PDFs (option b) only if bulk or emailed PDFs are ever needed.
- **Import runs synchronously**, capped at 1000 rows and 2 MB. A school-sized file takes seconds, so there's no job table and no need for a running `queue:work`.
- **Spreadsheets via OpenSpout** (`openspout/openspout`, pure PHP, streaming), wrapped in `App\Support\Spreadsheet` for both CSV and .xlsx.
  - **CSV:** written with a UTF-8 BOM so Excel reads Bangla names. The delimiter (`,` or `;`) is detected on import.
  - **Formula injection:** every exported value is written as a text cell, because OpenSpout's `Cell::fromValue()` would turn a string starting with `=` into a formula. CSV text that looks like a formula gets an apostrophe; plain numbers such as `+8801…` stay as they are.
- **Import tidying:**
  - Headers are matched loosely: "Date of birth", "DOB" and "date_of_birth" all work, and unknown columns are listed as ignored.
  - Dates can be `YYYY-MM-DD` or day-first `DD/MM/YYYY`, real Excel date cells, or Excel serial numbers (from a column formatted "General").
  - Gender accepts labels and M/F.
  - A 10-digit Excel number starting with 1 gets its dropped leading 0 back.
- **Import checks:**
  - Each row is validated with the add-student form's rules (`StudentRequest::fieldRules()`), plus: duplicate IDs within the file, class/section exist and match, roll free (database and file), and seats left in the section.
  - Rows with a typed-in roll are enrolled before rows given the next free roll, so the two can't collide.
- **The export doubles as an import file:** its headers match the import columns, and extra columns are ignored.
- **Certificate register:**
  - `certificates` keeps a serial number (`TC-2026-0001`, from `Sequence`) and an issue date.
  - Issuing again returns the same certificate, so reprints match; only the conduct remark can be corrected.
  - One TC per enrollment (unique type + enrollment). Archived students keep theirs.
- **Access:**
  - ID cards need `student.view`. Teachers get only their own sections (403) and students (404).
  - Viewing a TC needs `enrollment.view`; issuing needs `enrollment.edit`.
  - Import needs `student.create`, plus `enrollment.create` when rows carry a class.
  - Export needs `student.export` and applies `Student::visibleTo`.

## Database

- `certificates`:
  - Columns: `type` (`App\Enums\CertificateType`: transfer, testimonial), `serial_no` (unique), `student_id` (RESTRICT), `student_enrollment_id` (nullable, RESTRICT), `issued_on`, `issued_by` (users, null on delete), `conduct`, timestamps.
  - Indexes: unique (`type`, `student_enrollment_id`); index (`student_id`).

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /students/{id}/id-card | student.view | `?academic_year_id=` (default current); 422 if not actively enrolled; teachers: own students |
| GET | /sections/{id}/id-cards | student.view | active, non-archived students in roll order; teachers: own sections |
| GET | /enrollments/{id}/transfer-certificate | enrollment.view | 404 until issued |
| POST | /enrollments/{id}/transfer-certificate | enrollment.edit | `{conduct?}`; 422 unless transferred; 201 issued / 200 existing |
| POST | /students/import | student.create (+ enrollment.create) | multipart `file`, `dry_run`; 422 `errors["rows.N"]` per bad row |
| GET | /students/import/template | student.create | `?format=csv\|xlsx` |
| GET | /students/export | student.export | `format`, `academic_year_id`, `class_id`, `section_id`, `status` (`not_enrolled` too), `archived`, `search` |

## Backend tasks

- [x] `certificates` migration, `Certificate` model, `CertificateType` enum
- [x] `IdCardService` + `IdCardController`; `SchoolLetterhead`; `Student::photoUrl()` (shared with `StudentResource`)
- [x] `IssueTransferCertificate` + `TransferCertificateController`
- [x] `App\Support\Spreadsheet` (OpenSpout read/write, text cells, CSV-injection guard, Excel serial dates)
- [x] `ImportStudents` (header aliases, tidying, per-row validation reusing `StudentRequest::fieldRules()`, all-or-nothing commit + enroll) + `ImportStudentsRequest`
- [x] `StudentExport` + `StudentImportExportController` (template, import, export); routes before `students/{student}`

## Frontend tasks

- [x] `admin/id-cards` (class → section → printable sheet) + menu entry; `admin/students/[id]/id-card`; "ID card" button on the profile
- [x] `admin/enrollments/[id]/transfer-certificate` (issue with conduct, print, reprint); links from the student's Enrollment panel and the Enrollments list
- [x] `admin/students/import` (templates, column guide, Check file → per-row errors → Import, result table with generated IDs)
- [x] Export panel on the Students list (year/class/section/status, CSV or Excel)
- [x] `apiFetch({ asBlob })` + `lib/download.ts` for file downloads; print rules in `globals.css`

## Acceptance criteria

- [x] ID cards show the photo, IDs and current class/section/roll, and print cleanly (single and per section)
- [x] A TC can be produced only for a transferred enrollment, with its date and reason
- [x] Import validates every row and reports errors per row; nothing is saved when any row fails; enrollments are created for rows with class/section
- [x] Export honours the filters and includes class/section/roll; `student.export` is required
- [x] Teacher scoping (`Student::visibleTo`) applies to cards/exports for teacher callers
- [x] Tests written and passing (13: cards, scope, TC serials/permissions, import check/commit/errors/capacity/Excel/permissions, export filters/escaping/Excel/permission; 247 backend tests total)
- [x] Security review:
  - no PII beyond what the caller can already see;
  - uploads validated by extension + content, capped at 2 MB / 1000 rows;
  - exports guarded against formula injection;
  - signed photo links;
  - certificates are append-only (serial and date fixed).

## Build notes (2026-10-06)

- Clicked through in the browser:
  - **Students list:** new ID cards / Export / Import buttons. An Excel export downloaded (200).
  - **Import:**
    - A file with an impossible date (31/02) was refused on row 3 with one message.
    - After fixing it, "All 2 rows are ready…" then "Imported 2 students, 1 enrolled".
    - Generated IDs were shown, and the first row went to Class 7 A roll 1.
  - **ID cards:** 6A printed Nusrat's (photo via signed link) and Rafi's cards. The single-student card page works.
  - **Transfer certificate:**
    - Transferring the imported student out from the profile showed the "Transfer certificate →" link, and issuing produced TC-2026-0001.
    - The wording follows the record (pronoun, class, roll, destination), and a reload keeps the serial.
    - The Enrollments list links it too.
  - Test students, their certificate and its counter were removed afterwards.
- A school logo that fails to load is hidden on cards and certificates instead of printing as a broken image. In this dev setup the logo URL points at `APP_URL` (port 8000).
- **Noticed, not changed:** `config/app.php` fixes the app timezone to UTC while the school setting says Asia/Dhaka. Between 00:00 and 06:00 local time every "today" is the previous day (attendance, today's periods, certificate dates, default admission and leaving dates). Suggested as a follow-up task.
