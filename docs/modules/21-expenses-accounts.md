# Module 21 — Expenses & Basic Accounts

- **Status:** ⬜ Not started
- **Phase:** Phase 4 — Finance
- **Completed on:** —
- **Owner agent:** `module-builder`

> Records school expenses and other income under categories, and presents a basic accounting dashboard — total income vs. expense vs. balance — where fee payments (Module 20) automatically count as income, with date-range reports exportable to PDF/Excel/CSV.

## Scope

Includes (spec §19): expense CRUD with categories (salary, electricity, internet, maintenance, stationery, transport, other), other-income CRUD with categories (donations, admission, other), and a basic accounting dashboard showing Income / Expense / Balance. Fee payments from Module 20 are surfaced as income automatically (not re-entered). Date-range income/expense reports are exportable (PDF/Excel/CSV, spec §42), with large exports dispatched to a queue (spec §61). All monetary columns are `decimal(15,2)`, never float (spec §53).

Excludes: full double-entry accounting/ledgers (later expansion, spec §19); payroll (V2, spec §66); the payment write-path itself (Module 20). This module reads `payments` as income and never mutates them.

## Dependencies

`20 — Payments` (fee payments feed the income figure), `04 — School Settings` (currency/system settings, spec §8).

## Database (tables owned / referenced)

- `expenses` — id, expense_category_id (FK), amount `decimal(15,2)`, date, description, paid_to, recorded_by (FK users), timestamps, soft deletes. Index: (date), (expense_category_id).
- `expense_categories` — id, name (unique), is_active, timestamps.
- `income` — id, income_category_id (FK), amount `decimal(15,2)`, date, description, source, recorded_by (FK users), timestamps, soft deletes. Index: (date), (income_category_id).
- `income_categories` — id, name (unique), is_active, timestamps.
- References: `payments` (read-only, aggregated as fee income), `users`.

## Permissions

`accounts.view`, `accounts.create`, `accounts.report`. Two-layer authorization (spec §52): permission middleware gates the route; a policy restricts accounting data to accountant/admin roles (no parent/student/teacher exposure of financials).

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /expenses | accounts.view | paginated; filter category/date |
| POST | /expenses | accounts.create | |
| GET | /income | accounts.view | paginated; excludes/marks fee-payment income |
| POST | /income | accounts.create | manual (non-fee) income |
| GET | /expense-categories | accounts.view | |
| GET | /income-categories | accounts.view | |
| GET | /accounts/dashboard | accounts.view | income, expense, balance |
| GET | /accounts/reports | accounts.report | date-range; export PDF/Excel/CSV (queued) |

## Backend tasks

- [ ] Migrations + models for the four tables (FKs, indexes on date, soft deletes)
- [ ] Form Requests (validation + `authorize()`); amounts validated as decimal
- [ ] Policies restricting all accounting endpoints to finance roles
- [ ] `AccountsSummaryService`: income = manual income + Σ completed payments (minus refunds); expense = Σ expenses; `balance = income − expense` computed server-side
- [ ] Date-range report builder; `ExportReportJob` (queued) producing PDF/Excel/CSV
- [ ] API Resources for expense/income/dashboard shaping; routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] Admin `admin/accounts/`: expenses (list + form), income (list + form), dashboard (Income/Expense/Balance)
- [ ] Date-range report screen with export buttons (PDF/Excel/CSV) and job-status feedback
- [ ] Typed `accountsService` in `services/` (expenses, income, dashboard, reports); finance-role-only nav visibility

## Acceptance criteria

- [ ] Expense and income CRUD works with category assignment
- [ ] `balance = income − expense` is computed correctly on the dashboard
- [ ] Date-range report returns income and expense totals for the selected range
- [ ] Fee payments (Module 20) are reflected in the income figure automatically
- [ ] Reports are exportable to PDF/Excel/CSV, with large exports queued
- [ ] Tests written and passing (CRUD, balance calc, fee-income aggregation, date-range report)
- [ ] Security review passed (finance-only access, two-layer auth, no float money, financial exposure controlled)

## Notes

Fee income is derived from `payments` (completed minus refunded) so it is never double-counted or re-keyed — reflecting spec §68 "enter data once, use it everywhere." Balance is always server-computed. This is intentionally basic accounting (spec §19) and can later expand into full double-entry; payroll income/expense arrives with the V2 payroll module.
