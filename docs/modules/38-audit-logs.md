# Module 38 — Audit Logs

- **Status:** ⬜ Not started
- **Phase:** Phase 7 — Reporting, Communication & Audit
- **Completed on:** —
- **Owner agent:** `module-builder`

> Immutable audit trail (spec §43) recording who changed what, when, and from where — user, action, module, record, old/new values, IP, timestamp — captured automatically across critical modules, with special emphasis on financial and result operations (spec §43, §55).

## Scope

Includes (spec §43): a single `audit_logs` table recording user, action (create/update/delete/custom), module, target record, old value, new value, IP address, and timestamp. Capture is automatic via a reusable model trait + observer (and an event listener for non-model actions such as `payment.recorded` and `result.published`) so audited modules log without per-controller wiring. A read-only admin/principal viewer filters by user, module, action, and date range, paginated. Financial and result operations are always logged (spec §55).

Excludes: application error/access logging and monitoring (spec §63, infrastructure concern); the audit log is append-only and exposes no create/update/delete API — it is written by the system, never by users.

## Dependencies

`03 — Users` (actor identity). Hooks into `20 — Payments` and `18 — Results` (always-audited), and `04 — School Settings` and `11 — Students` (audited on change). New audited modules opt in by adding the trait — no change here.

## Database (tables owned / referenced)

- `audit_logs` — id, user_id (FK, nullable for system), action (string), module (string, e.g. `payments`), auditable_type + auditable_id (polymorphic record ref), old_values (json, nullable), new_values (json, nullable), ip_address (string), user_agent (nullable), created_at. Indexes: (user_id), (module, created_at), (auditable_type, auditable_id). No `updated_at`/soft deletes — rows are immutable.
- References: `users`; polymorphically references any audited model (payments, results, students, school_settings, …).
- **Already in place (2026-10-20, Modules 17–18):**
  - The `audit_logs` table with this schema.
  - The append-only `AuditLog` model.
  - `App\Support\Audit::record()`, which reads the IP and user agent from the request.
  - Explicit entries are already written for marks changes after entry, clears, submits and reopens, and for every results step (calculated → published / unpublished).
  - This module adds the `Auditable` trait/observer for automatic capture, wires the other modules, and builds the viewer.

## Permissions

`audit.view` only — held by Admin and Principal (spec §43). No create/edit/delete permission exists: logs are written by the system, and the table is immutable (no update/delete endpoints, enforced at the model and DB layer). Two-layer authorization (spec §52): the permission gates the viewer route; the viewer is otherwise read-only for everyone.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /audit-logs | audit.view | paginated; filter by user, module, action, date range |
| GET | /audit-logs/{id} | audit.view | single entry with old/new value diff |
| GET | /audit-logs/filters | audit.view | distinct modules/actions for filter dropdowns |

## Backend tasks

- [ ] Migration + `AuditLog` model (append-only: no `updated_at`, guard against update/delete)
- [ ] `Auditable` trait + model observer capturing created/updated/deleted with dirty old/new values
- [ ] Resolver for current user + IP + user-agent (from the authenticated request)
- [ ] Event listener for non-CRUD actions: `PaymentRecorded`, `ResultPublished` → always write a log (spec §55)
- [ ] Attach the trait to Payments, Results, Students, School Settings models
- [ ] Read-only viewer: `AuditLogService` (filtered, paginated query) + `AuditLogResource`
- [ ] Routes under `/api` with JWT + `permission:audit.view`; no write routes exist

## Frontend tasks

- [ ] Admin viewer under `admin/audit-logs/` — paginated table with filters (user, module, action, date range) and an old→new value diff panel
- [ ] Typed `auditLogService` in `services/` (list with filters, show)
- [ ] Visible only to users holding `audit.view` (Admin/Principal)

## Acceptance criteria

- [ ] A create/update/delete on an audited model writes a log with old + new values, user, and IP
- [ ] Payment and result-publish actions are always logged (spec §43, §55)
- [ ] The log is immutable — no API or DB path edits or deletes an entry
- [ ] The viewer filters (user, module, action, date) and paginates
- [ ] Only authorized roles (Admin/Principal via `audit.view`) can view logs
- [ ] Tests written and passing (auto-capture on CRUD, always-log for payment/result, immutability, filter/paginate, permission gating)
- [ ] Security review passed (two-layer auth, append-only enforcement, no sensitive value leakage in diffs)

## Notes

Automatic capture via a shared trait/observer plus event listeners keeps critical modules logging without per-controller code (spec §49). For payments the audit write is part of the transaction (spec §55) so a rolled-back payment leaves no orphan log. Old/new value JSON should redact secrets (password hashes, tokens) before storage. Adding a new audited module is one trait, no change to this module.
