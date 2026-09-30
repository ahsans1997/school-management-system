# Module 03 — Users

- **Status:** ✅ Complete
- **Phase:** Phase 1 — Foundation
- **Completed on:** 2026-09-30

> Admin management of user accounts: create/edit/delete, assign roles, activate/deactivate, admin-initiated password reset, and linking a user to a person record (student/teacher/guardian/employee).

## Scope

Includes: user CRUD (paginated list), role assignment to users, activate/deactivate toggle, password reset performed by an admin, and a polymorphic link from a user to a person record. Enforces the two authorization layers — `user.*` permission plus an ownership policy preventing self-privilege escalation.

Excludes: self-service registration and forgot-password (deferred), role/permission definitions (Module 02), the person records themselves (owned by their feature modules — this module only stores the link).

## Dependencies

`02 — Roles & Permissions`.

## Database (tables owned / referenced)

- Extends `users` (Module 01): added nullable polymorphic link `personable_type` / `personable_id` (via `nullableMorphs` with a composite index). `is_active` already present.
- References Spatie `model_has_roles` for role assignment; the polymorphic link targets future person tables.

## Permissions

Uses `user.view`, `user.create`, `user.edit`, `user.delete` (seeded in Module 02). Ownership rule (`UserPolicy`): a user may not assign roles to, toggle status of, or delete **themselves** — regardless of permission. Role names are validated server-side against existing roles.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /users | user.view | paginated; `search`, `role`, `is_active` filters |
| POST | /users | user.create | create with roles + optional person link |
| GET | /users/{id} | user.view | with roles |
| PUT | /users/{id} | user.edit | update profile (name/email/person link) |
| DELETE | /users/{id} | user.delete | soft delete (not self) |
| POST | /users/{id}/assign-role | user.edit | replace roles (not self) |
| PATCH | /users/{id}/toggle-status | user.edit | activate/deactivate (not self) |
| POST | /users/{id}/reset-password | user.edit | admin reset; returns a temp password if none supplied |

All under `['auth:api', 'token.type:access', 'active']`.

## Backend tasks

- [x] Migration adding polymorphic person link + index; `User` model gains `personable()` + `PERSON_TYPES`
- [x] Form Requests (validation + `authorize()`); role names validated against DB; person-type allowlisted
- [x] `UserPolicy` (record-level: cannot assign-role/toggle-status/delete self)
- [x] Actions: create, update, assign-role, toggle-status, reset-password, delete (no logic in controllers)
- [x] `ManagedUserResource` (never exposes the password hash)
- [x] Routes under `/api` with JWT + `permission:` middleware
- [x] Deactivated users blocked at login (Module 01 `LoginAction`) **and** immediately via new `active` middleware (even with a valid token)

## Frontend tasks

- [x] `admin/users` page (guarded by `RequireAuth permission="user.view"`) — paginated table, search, create form
- [x] Role select (per-row assign), status toggle, reset-password with confirm (shows temp password)
- [x] Typed `userService` in `services/`
- [x] Controls gated by `user.create|edit|delete`; self-row destructive actions hidden (mirrors the policy)

## Acceptance criteria

- [x] Admin creates a user with an assigned role (verified live: created "Library Staff" with Librarian)
- [x] A deactivated user cannot log in (and existing tokens are rejected immediately)
- [x] User list is paginated
- [x] A user cannot escalate their own role from the client (403 via `UserPolicy`)
- [x] Tests written and passing (13 user feature tests, 37 assertions; 37 backend tests total)
- [x] Security review passed (two-layer auth, no hash exposure, server-validated roles, self-protection)

## Notes

The polymorphic person link realizes "enter data once, use it everywhere" (spec §68). Admin password reset issues a temporary credential (returned once); self-service reset deferred to the communication module.

**Build notes (2026-09-30):**
- The person link's target models (Student/Teacher/Guardian/Employee) don't exist yet, so the API accepts a type from an allowlist (`teacher|student|guardian|employee`) + integer id and stores them; existence validation and the morph map are wired when those modules land. `personable()` resolves to null until then.
- New `active` middleware (alias `active`) on the whole authenticated feature group rejects deactivated accounts immediately — deactivation no longer waits for token expiry.
- Verified end-to-end in the browser: admin → `/admin/users` lists users, role dropdown populated, **self-row shows only "reset pw"** (no deactivate/delete), and creating a user through the form persisted with the chosen role (API confirmed 2 users). Browser render-throttling while the window is hidden made the post-create table refresh look stale, but the data and API were correct.
- A dev user `librarian1@school.test` (Librarian, password `password123`) was left in the dev DB from the live test — handy as a non-admin account.
