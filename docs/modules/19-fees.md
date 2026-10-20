# Module 19 — Fees Management

- **Status:** ✅ Complete
- **Phase:** Phase 4 — Finance
- **Completed on:** 2026-10-21
- **Owner agent:** `module-builder`

> Defines fee types and per-class/year fee structures, then generates student invoices (per month/period) with correct line items, totals, and server-computed dues — the billing backbone that Payments (Module 20) settles against.

## Scope

Includes (spec §18):
- A fee type catalogue: admission, tuition, exam, transport, library, hostel, other.
- Fee-structure setup: per class + academic year amounts.
- Invoice generation per student per month, with itemised line items.
- Partial-payment-aware totals and due tracking.

Batch invoice generation for a whole class or section is dispatched to a queue (spec §61). All monetary columns are `decimal(15,2)`, never float (spec §53).

Excludes:
- Recording payments, receipts and refunds (Module 20 — Payments).
- Expense/income accounting (Module 21).
- Notifications and fee reminders (deferred to Communication).

Invoice `paid`/`due` are only ever changed by the Payments module, inside a transaction. This module computes them at generation time and reads them thereafter.

## Dependencies

`06 — Academic Years`, `11 — Students`, `13 — Enrollments` (invoices target enrolled students; fee structures resolve by the student's class + academic year).

## Database (tables owned / referenced)

**`fee_types`:** id, name (unique), code (unique, upper-cased), category (`FeeCategory`), is_active, timestamps.
- A fee type in use is **turned off, not deleted**: structures and invoice lines reference it with RESTRICT.
- This replaces the planned soft deletes.

**`fee_structures`:** id, fee_type_id, class_id, academic_year_id, amount `decimal(15,2)`, frequency, is_active, timestamps. Unique (fee_type_id, class_id, academic_year_id).
- `monthly`: on every monthly invoice.
- `one_time`: once per academic year, on the first invoice that includes it.
- `exam`: only on invoices it's chosen for.

**`fee_invoice_runs`** (new): one "generate" request.
- Columns: year, class, optional section, period, due_date, the chosen fee types, status (queued → running → completed / failed), created/skipped counts, total, error, requested_by, timestamps.
- The screen polls it for progress.

**`fee_invoices`:**
- Columns: invoice_no (unique, `INV-YYYY-NNNNN` from `Sequence`), student_id, student_enrollment_id, academic_year_id, class_id, fee_invoice_run_id, period (`YYYY-MM`), due_date, total / paid / due `decimal(15,2)`, status (`InvoiceStatus`: unpaid / partial / paid / void), issued_at/by, voided_at/by, void_reason, timestamps.
- **No deletes and no soft deletes**: a mistaken invoice is voided.
- A stored generated `live_period` (NULL once void) makes the unique key (student_id, academic_year_id, live_period). That means one live invoice per student per month, and a voided month can be invoiced again.
- `issued_at` is a DATETIME: MariaDB gives the first NOT NULL TIMESTAMP of a table ON UPDATE CURRENT_TIMESTAMP, which would rewrite it on every change.
- MySQL/MariaDB also enforce `CHECK (total > 0 AND paid >= 0 AND paid <= total AND due = total - paid)`.

**`fee_invoice_items`:** fee_invoice_id, fee_type_id and fee_structure_id (all RESTRICT: invoices are never deleted, so neither are their lines), description, amount `decimal(15,2)`, timestamps.

**References:** `students`, `student_enrollments`, `classes`, `sections`, `academic_years`, `users`, `audit_logs`.

## Permissions

| Permission | Allows |
|---|---|
| `fee.view` | Lists and invoices, plus the `/fees/options` pickers, so finance staff don't need class/year rights |
| `fee.create` | Fee types, structures, generating invoices, voiding |
| `fee.report` | The due report |

Default roles: Accountant and Admin hold all of these; Principal has view and report.

Two-layer (spec §52):
- `FeeInvoicePolicy` keeps Parent/Student logins off the staff invoice endpoints (list, show, generate, runs, void, options, due report), even if a role edit gives them `fee.view`.
- Students and parents hold no `fee.*` permission. They see only their own (or their children's) invoices, through ownership-only `/fees/me` and the `my_fees` / `child_fees` widgets.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /fee-types | fee.view | paginated; `?active=1` |
| POST / PUT / DELETE | /fee-types[/{id}] | fee.create | delete 409 once used (turn it off) |
| GET | /fee-structures | fee.view | paginated; `?academic_year_id`, `?class_id` |
| POST / PUT / DELETE | /fee-structures[/{id}] | fee.create | type/class/year fixed after create; audited; delete 409 once billed |
| GET | /fee-invoices | fee.view | paginated + `totals` (billed/collected/due); year, class, section, month, status (`outstanding` = unpaid + partial), search (name, student ID, invoice no.) |
| GET | /fee-invoices/{id} | fee.view | with lines + letterhead |
| POST | /fee-invoices/generate | fee.create | **202** + the queued run; the request carries no amounts |
| GET | /fee-invoices/runs, /fee-invoices/runs/{id} | fee.view | run progress |
| POST | /fee-invoices/{id}/void | fee.create | reason required; 409 once anything is paid; audited |
| GET | /fees/reports/due | fee.report | outstanding by student, most owed first; `?overdue=1` |
| GET | /fees/options | fee.view | years, classes + sections, fee types, currency |
| GET | /fees/me | (ownership) | a student's / parent's own invoices + total due |

## Backend tasks

- [x] Migration + models (`FeeType`, `FeeStructure`, `FeeInvoiceRun`, `FeeInvoice`, `FeeInvoiceItem`; `FeeCategory`, `FeeFrequency`, `InvoiceStatus`)
- [x] Form Requests (`FeeTypeRequest`, `FeeStructureRequest`, `GenerateInvoicesRequest`, `VoidInvoiceRequest`): amounts validated as decimals with ≤ 2 places and never used as totals
- [x] Scope: staff by permission; students and parents only via `/fees/me` (ownership)
- [x] `GenerateInvoices` (`queue()` + `process()`):
  - Resolves the class's active structures for the year.
  - Builds lines, total = Σ lines and due = total, in integer paisa (`App\Support\Money`).
  - Skips students already invoiced for the month, inactive enrollments and archived students.
  - One transaction per student, which first locks the student's row. Two runs can't both bill a one-time fee or race on the same month, and deadlocks are retried (3 attempts).
  - Only a real duplicate (a live invoice for the month) is skipped on a unique clash.
  - A second run for the same class + month is refused (409) while one is queued or running. Runs older than 15 minutes don't block.
- [x] `GenerateInvoicesJob` (queued, `tries = 1`, 80 s timeout, below `retry_after`):
  - `failed()` marks the run failed with its counts, a plain message (details go to the log) and a `fees.invoices_generation_failed` audit entry.
  - The request itself is audited (`fees.generation_requested`, with the IP).
- [x] `VoidInvoice`, `SaveFeeStructure`, `SaveFeeType`. Fee type and structure changes, generation and voids are audited.
- [x] API Resources (money as `"1500.00"` strings); routes under `/api` with JWT + `permission:` middleware
- [x] Real `pending_fees` (admin/staff), `my_fees` (student) and `child_fees` (parent) widgets replace their placeholders

## Frontend tasks

- [x] `admin/fees` (Fee setup):
  - Class fees per year + class: inline amount, frequency, active; add/remove; the monthly total.
  - Fee types: add, edit, turn off, delete.
- [x] `admin/fees/invoices`:
  - Filters, totals tiles, list.
  - Generate panel: class/section/month/due date, usual fees or chosen ones. It polls the run, and hints at `php artisan queue:work` if it stays queued.
- [x] `admin/fees/invoices/[id]`: printable invoice (letterhead, lines, total/paid/due), void with a reason
- [x] `admin/fees/dues`: due report (year, class, overdue only), print
- [x] `student/fees` and `parent/fees`: own invoices, total due, printable invoice; dashboard cards (admin "Fees outstanding" tile, student/parent fees cards)
- [x] Typed `feeService` in `services/`; `formatMoney` (৳, South Asian grouping) and `formatPeriod` in `lib/format`; nav (Finance: Invoices, Due report, Fee setup; portals: Fees)

## Acceptance criteria

- [x] Fee structure defines per-class, per-year fee amounts
- [x] Generated invoice contains correct line items and a total equal to their sum
- [x] `due = total − paid` is computed server-side (never accepted from client)
- [x] Batch invoice generation runs as a queued job
- [x] Duplicate invoice for the same (student, period, year) is prevented by the unique constraint
- [x] Tests written and passing:
  - 5 fee tests: structures, generation, the queued job, one-time and exam fees, skip and idempotency, the unique index, void, lists and totals, the due report, ownership, widgets, the policy, concurrent-run guard, failure trail, money format.
- [x] Security review passed (two-layer auth, amounts server-derived, no float money, ownership scope). Findings fixed:
  - H1: MariaDB's automatic ON UPDATE on `issued_at` (and Module 18's `results.calculated_at`) → DATETIME (results via a follow-up migration).
  - M1: concurrent runs could deadlock or bill a one-time fee twice → student row lock, retries, one active run per class + month.
  - M2: a failed run left untraceable invoices → counts kept, failure audited, job `failed()` hook, request audited with IP, timeout below `retry_after`.
  - M3: no policy → `FeeInvoicePolicy`.
  - Low: money must match `^\d{1,13}(\.\d{1,2})?$` (`FeeStructureRequest::MONEY`); only real duplicates are swallowed; no raw SQL errors on screen; an edit can't silently re-activate a fee; fee-type changes audited; LIKE wildcards escaped.
  - Done ahead of Payments: DB CHECK on amounts, `InvoiceStatus::for()` refuses overpayment/negative, invoice lines RESTRICT.

## Notes

- **`paid`, `due` and `status`:** set to 0 / total / unpaid at generation. From then on only Payments (Module 20) changes them, in a transaction: create payment → `paid` → `due` → `status` (`InvoiceStatus::for()`) → receipt → audit. Voiding is refused once anything is paid.
- **Not modelled yet:**
  - Per-student opt-in fees (transport/hostel users): structures apply to the whole class. The Transport / Hostel modules will add opt-in.
  - Waivers and scholarships.
  - Late fines.
- **The queue worker must be running:** `php artisan queue:work`, with `QUEUE_CONNECTION=database`. Otherwise runs stay "queued" and the screen says so. Tests run the queue inline.
- **Not clicked through in the browser by Claude:** the user runs the dev servers. Checked instead:
  - Feature tests.
  - Larastan (0 errors).
  - ESLint and `tsc`.
  - A full generate → re-run → summary → due report on the dev MariaDB inside a rolled-back transaction.
