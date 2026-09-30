# Module 19 — Fees Management

- **Status:** ⬜ Not started
- **Phase:** Phase 4 — Finance
- **Completed on:** —
- **Owner agent:** `module-builder`

> Defines fee types and per-class/year fee structures, then generates student invoices (per month/period) with correct line items, totals, and server-computed dues — the billing backbone that Payments (Module 20) settles against.

## Scope

Includes (spec §18): fee type catalogue (admission, tuition, exam, transport, library, hostel, other), fee-structure setup (per class + academic year amounts), invoice generation per student per month/period, itemised line items, partial-payment-aware totals, and due tracking. Batch invoice generation for a whole class/section is dispatched to a queue (spec §61). All monetary columns are `decimal(15,2)`, never float (spec §53).

Excludes: recording payments, receipts, and refunds (Module 20 — Payments); expense/income accounting (Module 21); notifications/fee reminders (deferred to Communication). Invoice `paid`/`due` are only ever mutated by the Payments module inside a transaction — this module computes them at generation time and reads them thereafter.

## Dependencies

`06 — Academic Years`, `11 — Students`, `13 — Enrollments` (invoices target enrolled students; fee structures resolve by the student's class + academic year).

## Database (tables owned / referenced)

- `fee_types` — id, name, code (unique), category enum (admission/tuition/exam/transport/library/hostel/other), is_active, timestamps, soft deletes.
- `fee_structures` — id, fee_type_id (FK), class_id (FK), academic_year_id (FK), amount `decimal(15,2)`, frequency (monthly/one_time/exam), is_active, timestamps. Unique (fee_type_id, class_id, academic_year_id).
- `fee_invoices` — id, student_id (FK), academic_year_id (FK), period (month/term), total `decimal(15,2)`, paid `decimal(15,2)` default 0, due `decimal(15,2)`, status enum (unpaid/partial/paid/void), issued_at, timestamps, soft deletes. Indexes: (student_id), (status), unique (student_id, period, academic_year_id).
- `fee_invoice_items` — id, fee_invoice_id (FK), fee_type_id (FK), description, amount `decimal(15,2)`, timestamps.
- References: `students`, `student_enrollments`, `classes`, `academic_years`.

## Permissions

`fee.view`, `fee.create`, `fee.report`. Two-layer authorization (spec §52): permission middleware gates the route, and a policy enforces scope (e.g. a parent/student may view only their own invoices; accountants view all).

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /fee-types | fee.view | paginated |
| POST | /fee-types | fee.create | |
| GET | /fee-structures | fee.view | paginated; filter by class/year |
| POST | /fee-structures | fee.create | per class + academic year |
| GET | /fee-invoices | fee.view | paginated; filter student/period/status |
| GET | /fee-invoices/{id} | fee.view | includes line items; policy-scoped |
| POST | /fee-invoices/generate | fee.create | batch per class/section → queued job |
| GET | /fees/reports/due | fee.report | outstanding dues report |

## Backend tasks

- [ ] Migrations + models for the four tables (FKs, unique constraints, indexes, soft deletes)
- [ ] Form Requests (validation + `authorize()`); amounts validated as decimal, never trusted for computation
- [ ] Policies: invoice visibility scoped to owner (parent/student) vs. accountant
- [ ] Actions/Services: `GenerateInvoiceAction` resolves structure by class/year, builds items, computes `total = Σ items` and `due = total − paid` server-side
- [ ] `GenerateClassInvoicesJob` (queued) for batch generation
- [ ] API Resources for invoice + items shaping; routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] Admin pages under `admin/fees/`: fee types, structures, invoices (paginated list + detail); batch-generate UI with job-status feedback
- [ ] Typed `feeService` in `services/` (types, structures, invoices, generate, due report)
- [ ] Parent/student read-only invoice views scoped to own records

## Acceptance criteria

- [ ] Fee structure defines per-class, per-year fee amounts
- [ ] Generated invoice contains correct line items and a total equal to their sum
- [ ] `due = total − paid` is computed server-side (never accepted from client)
- [ ] Batch invoice generation runs as a queued job
- [ ] Duplicate invoice for the same (student, period, year) is prevented by the unique constraint
- [ ] Tests written and passing (structure resolution, invoice totals, due calc, batch job)
- [ ] Security review passed (two-layer auth, amounts server-derived, no float money, ownership scope)

## Notes

Invoice `total` is derived from `fee_invoice_items`; client-submitted totals/amounts are ignored (spec §51). `paid`/`due` are advanced only by Module 20 within a DB transaction (spec §55). Fee reminders and receipts are out of scope here.
