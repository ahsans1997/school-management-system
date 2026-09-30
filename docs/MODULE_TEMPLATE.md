# Module NN — <Module Name>

- **Status:** ⬜ Not started
- **Phase:** <phase number and name>
- **Completed on:** —
- **Owner agent:** `module-builder`

> One-line description of what this module delivers and why it exists.

## Scope

What this module includes (and explicitly what it does *not* — deferred to another module or V2).

## Dependencies

Modules that must be complete before this one starts (e.g. `02 — Roles & Permissions`). List them so the build order is enforceable.

## Database (tables owned / referenced)

- `table_name` — purpose, key columns, notable indexes/constraints. Money columns are `decimal(15,2)`.
- References: tables from other modules this one reads/writes.

## Permissions

Spatie permissions this module introduces (e.g. `student.view`, `student.create`, `student.edit`, `student.delete`, `student.export`) and, where relevant, the ownership/scope rule beyond the permission.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /... | ... | paginated |

## Backend tasks

- [ ] Migrations + models (FKs, indexes, soft deletes as needed)
- [ ] Form Requests (validation + `authorize()`)
- [ ] Policies (record-level authorization)
- [ ] Actions/Services (business logic; transactions for money)
- [ ] API Resources (response shaping)
- [ ] Routes under `/api` with JWT + `permission:` middleware
- [ ] Jobs/Events/Notifications where applicable

## Frontend tasks

- [ ] Pages under the correct role area (`admin/`, `teacher/`, `parent/`, `student/`, `(public)/`)
- [ ] API client methods in `services/` (typed, JWT-aware)
- [ ] List (paginated) + create/edit/view forms with validation & error states
- [ ] Role-appropriate visibility

## Acceptance criteria

- [ ] Criterion 1 (specific, testable)
- [ ] Criterion 2
- [ ] Tests written and passing (backend feature tests; frontend where applicable)
- [ ] Security review passed (auth two-layer, input trust, uploads, financial/public exposure as relevant)

## Notes

Design decisions, open questions, deferrals.
