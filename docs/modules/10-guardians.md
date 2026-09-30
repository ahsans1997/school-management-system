# Module 10 — Guardians

- **Status:** ⬜ Not started
- **Phase:** Phase 2 — Academic Core
- **Completed on:** —
- **Owner agent:** `module-builder`

> Manages guardians/parents (spec §20) who can each have multiple children, with a linking pivot that records relationship and a primary-guardian flag, plus an optional Parent login account.

## Scope

Includes: CRUD for guardians; linking/unlinking a guardian to one or more students via `student_guardians` (relationship + `is_primary`); optionally creating a login account (Parent role) so a guardian can access the parent portal scoped to their own children only.

Excludes: the parent portal screens themselves (Module 33) and student records (Module 11 — this module links to them). Messaging/notifications are later modules.

## Dependencies

- `03 — Users` (optional Parent login account + Spatie Parent role). Student links are wired when Module 11 — Students exists.

## Database (tables owned / referenced)

- `guardians` — `id`, `user_id` (nullable FK → `users`, for optional login), `name`, `phone`, `email` (nullable), `occupation` (nullable), `address` (nullable), `photo` (nullable), timestamps. Index on `phone`, `user_id`.
- `student_guardians` (pivot) — `id`, `student_id` (FK → `students`), `guardian_id` (FK → `guardians`), `relationship` (e.g. father/mother/uncle), `is_primary` (boolean), timestamps. Unique (`student_id`,`guardian_id`); indexes on each FK.
- References: `users`, `students`.

## Permissions

Spatie permissions: `guardian.view`, `guardian.create`, `guardian.edit`, `guardian.delete`. Admin/Receptionist manage. Parent-role users are scoped by ownership policy to their linked children only.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /guardians | guardian.view | paginated list |
| POST | /guardians | guardian.create | create (+ optional login account) |
| GET | /guardians/{id} | guardian.view | show with linked students |
| PUT | /guardians/{id} | guardian.edit | update |
| DELETE | /guardians/{id} | guardian.delete | soft/guarded delete |
| POST | /guardians/{id}/students | guardian.edit | link student (relationship, is_primary) |
| DELETE | /guardians/{id}/students/{studentId} | guardian.edit | unlink |

## Backend tasks

- [ ] Migrations + `Guardian` model and `student_guardians` pivot (FKs, unique, indexes)
- [ ] Form Requests (contact validation; single primary per student; `authorize()`)
- [ ] Policy (Parent role sees only own children — ownership layer beyond permission)
- [ ] Action/Service: create-with-optional-user, link/unlink (no logic in controllers)
- [ ] Assign Parent role via Spatie when a login account is created
- [ ] API Resources + routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] Admin pages `admin/guardians/` (paginated list, CRUD, link/unlink students)
- [ ] Typed API client methods in `services/`
- [ ] Link UI (relationship, primary toggle); optional "create login" with validation
- [ ] Role-appropriate visibility; parent area consumes only own-children data

## Acceptance criteria

- [ ] A guardian can be linked to multiple students; a student to multiple guardians
- [ ] Exactly one primary guardian can be designated per student
- [ ] A Parent login sees only their linked children (ownership policy enforced)
- [ ] List endpoint is paginated (`?page&per_page`)
- [ ] Tests written and passing (feature tests for linking, primary, parent scoping)
- [ ] Security review passed (two-layer auth: permission + ownership policy)

## Notes

Two-layer authorization is essential here (spec §52): `guardian.view` alone is not enough — the policy restricts Parent users to their own children. Login account creation reuses Module 03 user provisioning. Password entry/credential handling stays server-side.
