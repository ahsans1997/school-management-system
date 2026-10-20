# Module 02 — Roles & Permissions

- **Status:** ✅ Complete
- **Phase:** Phase 1 — Foundation
- **Completed on:** 2026-09-30

> Seeds the granular Spatie permission set and the school's roles, and exposes role CRUD plus permission-assignment endpoints so access can be managed without code changes.

## Scope

Includes (spec §5, §6, §52): role CRUD, listing all permissions, assigning permissions to roles, and seeding the baseline roles and granular permissions. Establishes the `permission:` middleware as the first authorization layer used by every later module.

Excludes: assigning roles to individual users and user management (Module 03), record-level ownership policies (owned by each feature module — this module only supplies the permission layer).

## Dependencies

`01 — Authentication (JWT)`.

## Database (tables owned / referenced)

- Uses the Spatie tables published in Module 00: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`. All rows use guard `api`.
- References `users` (Module 01) as the role/permission subject (User now uses the `HasRoles` trait).

## Permissions

Catalogue is server-defined in [config/permissions.php](../../backend/config/permissions.php) — **82 permissions across 24 domain groups** (80 at launch; Module 16 added `exam.grading`, Module 18 `result.process`) (dashboard, user, role, permission, settings, academic_year, class, section, subject, guardian, student, teacher, enrollment, attendance, routine, exam, result, fee, accounts, report, notification, audit, admission, website). Introduces its own: `role.view|create|edit|delete`, `permission.view`.

Seeds **11 roles**: Admin, Principal, Teacher, Accountant, Receptionist, Librarian, Transport Manager, HR Manager, Staff, Parent, Student. Admin receives all permissions; the others get sensible starting sets (editable via the API).

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /roles | role.view | paginated |
| POST | /roles | role.create | create role (optional permissions) |
| GET | /roles/{id} | role.view | with assigned permissions |
| PUT | /roles/{id} | role.edit | rename (blocked for system roles) |
| DELETE | /roles/{id} | role.delete | blocked for system roles |
| GET | /permissions | permission.view | full list grouped by domain |
| PUT | /roles/{id}/permissions | role.edit | sync permissions (Admin locked) |

All under the `['auth:api', 'token.type:access']` group.

## Backend tasks

- [x] `RolePermissionSeeder` creating all permissions + roles from config; Admin gets all
- [x] Form Requests: `StoreRoleRequest`, `UpdateRoleRequest`, `SyncRolePermissionsRequest` (permission names validated against the DB — never trusted)
- [x] Actions: `CreateRoleAction`, `UpdateRoleAction`, `DeleteRoleAction`, `SyncRolePermissionsAction` (no logic in controllers)
- [x] `RoleResource`; grouped permission list from `PermissionController`
- [x] Routes under `/api` with `auth:api` + `permission:` middleware (Spatie aliases registered in `bootstrap/app.php`)
- [x] `SystemRoles` guard: system roles can't be deleted/renamed; Admin permissions immutable

## Frontend tasks

- [x] `admin/roles` page (guarded by `RequireAuth permission="role.view"`)
- [x] Permission-assignment UI — grouped checkboxes, save + error states (`RolesManager`)
- [x] Typed `roleService` in `services/`
- [x] Controls gated by `role.create` / `role.edit` / `role.delete` via `useAuth().can()`; `me`/`login` now return the user's `roles` + `permissions`

## Acceptance criteria

- [x] Seeder creates all roles and permissions; Admin holds every permission (verified: 80 perms, Admin = 80)
- [x] A role can be assigned/synced a set of permissions
- [x] `permission:` middleware gates a route (403 without the permission)
- [x] Role list is paginated
- [x] Tests written and passing (10 role/permission feature tests, 29 assertions; 24 backend tests total)
- [x] Security review passed (system roles protected, permission names server-defined & DB-validated, Admin immutable)

## Notes

Permissions are the first of the two authorization layers (spec §52); ownership/scope policies are added per feature module. Permission names are canonical and defined only in `config/permissions.php` — never accepted from the client.

**Build notes (2026-09-30):**
- Roles/permissions use guard **`api`** (matches the JWT guard). Seeding gotcha: call `PermissionRegistrar::forgetCachedPermissions()` **between** creating permissions and assigning them to roles, or `syncPermissions` throws `PermissionDoesNotExist` from a stale in-memory cache.
- `UserResource` extended to include `roles` + `permissions` so the frontend can gate UI (additive — Module 01 tests unaffected).
- Verified end-to-end in the browser: logged in as admin → `/admin/roles` (RBAC-gated) lists all 11 roles; selecting Teacher shows exactly its 11 permissions checked out of 80.
- Dev admin (`admin@school.test`) is assigned the Admin role by `DatabaseSeeder`.
