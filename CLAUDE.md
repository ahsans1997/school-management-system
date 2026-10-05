# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project status

**Module 00 (Project Setup) is complete** — the two apps are scaffolded and boot:

- `backend/` — Laravel 13.32 API (PHP 8.3), JWT + Spatie Permission installed, MySQL, cache/queue on the database driver, `GET /api/health` live, OpenAPI docs at `/docs/api`. See [backend/CLAUDE.md](backend/CLAUDE.md).
- `frontend/` — Next.js 16 (TypeScript, App Router, Tailwind 4) with the spec §48 folder structure and a typed API client stub in `src/services/`.
- `school_management_system_full_architecture.md` — authoritative product & architecture spec.
- `docs/` — module-by-module build plan and the live completion tracker ([docs/PROGRESS.md](docs/PROGRESS.md)).
- `.claude/agents/` — specialized subagents for building this system.

**Modules 01–05 complete — Phase 1 (Foundation) done.** Dashboard: role-aware `GET /api/dashboard` (widget registry; audiences admin/staff/teacher/parent/student) + admin shell with permission-filtered nav + teacher/parent/student portals + post-login routing by audience. School Settings: cached singleton `GET|PUT /api/settings` + multipart `POST /api/settings/logo|favicon` (encrypted, masked secret groups) + `admin/settings` UI. Auth (JWT): `POST /api/auth/login|refresh|logout`, `GET /api/auth/me` (access + refresh tokens, `auth:api` + `token.type` middleware, login throttling). RBAC: 80 permissions / 11 roles from `backend/config/permissions.php`, `permission:` middleware, role CRUD + `admin/roles` UI. Users: CRUD + assign-role + toggle-status + reset-password API (`UserPolicy` blocks self-escalation; `active` middleware rejects deactivated accounts immediately) + `admin/users` UI. Protected feature routes sit under `['auth:api','token.type:access','active']`. Demo accounts (seeded only when `APP_ENV=local`, password `password` for all): `admin@`, `teacher@`, `parent@`, `student@`, `accountant@school.test`. **Module 06 (Academic Years) complete** — CRUD + atomic `POST /api/academic-years/{id}/set-current`; one current year enforced by a DB unique index on a generated column; `admin/academic-years` UI. **Module 07 (Classes) complete** — shared class catalogue (unique name, `numeric_level` ordering/promotion, Shift/Medium enums) + `admin/classes` UI. **Module 08 (Sections) complete** — sections nested under a class (`/classes/{id}/sections` to list/create, `/sections/{id}` after), unique name per class, `capacity` (enforced at enrollment), class fixed at creation; `admin/classes/[classId]/sections` UI. **Module 09 (Subjects) complete** — subject catalogue (unique name, optional unique code, `SubjectType` enum) + `class_subjects` assignment (`/classes/{id}/subjects`, gated by `subject.edit`); no teacher link yet (Module 12 adds it, possibly per section); `admin/subjects` and `admin/classes/[classId]/subjects` UIs. **Module 10 (Guardians) complete** — guardian register with search + optional Parent login (created with the guardian or later; needs `guardian.edit`; role fixed server-side) + `admin/guardians` UI; student links + parent scoping moved to Module 11. **Module 11 (Students) complete** — student register (generated admission no. `YYYY-NNNN` + random 8-digit student ID, search, archive/restore via soft delete), private photo + documents via signed links, guardian links with exactly one primary per student, real `student_stats` + parent `children` dashboard widgets; `admin/students` list/new/profile/edit UIs. Transfer, ID cards, certificates and import/export moved to Module 13 (they need enrollments). **Module 12 (Teachers) in review** — built and API-verified, pending a browser click-through: teacher register (generated `T-0001` employee IDs, DECIMAL salary, archive refused until classes are handed over, which also disables the login), Teacher-role logins, per-section teaching assignments (`teaching_assignments` → `class_subjects`) + `sections.class_teacher_id`, `teacher_stats` + teacher `assigned_subjects` widgets; `admin/teachers` UIs. **Module 13 (Enrollments) in review** — same status: `student_enrollments` (class derived from the section + composite FK; one per student/year; roll unique per section/year; capacity counts active, non-archived students), status lifecycle (transfer/left/graduated with date + reason), all-or-nothing cohort promotion one class up, **teacher scoping** of the student list/profile (`Student::visibleTo`), `admin/enrollments` UI + student Class column/Enrollment panel. ID cards, certificates and import/export split into **13b**. **Module 14 (Attendance) in review** — same status: one sheet per section/day (idempotent bulk upsert, whole roll required, roll as of the date), P/A/L/Lv with percentage = (present+late)/marked, monthly grid + student report, teacher scope via `App\Support\TeacherScope` (403 outside their sections), attendance widgets for all four audiences, teacher-portal nav; teacher/staff attendance deferred to HR. **Module 15 (Class Routine) in review** — same status: per-section weekly routine whose teaching periods reference `teaching_assignments` (labelled breaks otherwise), save blocked on teacher double-booking / room clash / own-period overlap (time-range overlap, structured `clashes` in the 422), section/teacher/room views + ownership-based `/routines/me` for teacher/student/parent portals, today's-periods widgets. Next: click through 12–15 in a browser, then **13b** or **16 — Exams**.

> **PHP gotcha:** the system-default `php` is 7.4 (XAMPP) and cannot run Laravel 13. Use Laragon's PHP 8.3 — prepend `/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64` to PATH before `php`/`composer`. Details in [backend/CLAUDE.md](backend/CLAUDE.md).

## What this product is

A **School Management System (SMS) + public School Website CMS** for a single school, sharing one backend and database. Two surfaces on the same Laravel API:

- **Private management area** — admin, principal, teacher, accountant, librarian, staff, parent, student portals.
- **Public website** — homepage, notices, events, gallery, admission, public results, fully managed from the CMS (no developer needed).

Guiding principle from the spec: **"Enter data once, use it everywhere."** A teacher, notice, or result entered once flows to management, portals, the public website, and notifications. Avoid duplicating data across modules.

## Technology stack (target)

| Layer | Choice |
|---|---|
| Backend | Laravel 13 (PHP), REST API under `/api` |
| Auth | JWT (access + refresh tokens), `Authorization: Bearer <token>` |
| Authorization | Spatie Laravel Permission (granular permissions, not broad roles) |
| Database | MySQL 8.x, InnoDB, `utf8mb4` |
| Cache/Queue | Laravel Cache + Queue on the `database` driver (`file` cache option); Laravel Scheduler. Redis deferred — env-only switch later |
| Frontend | Next.js (latest stable), TypeScript, App Router |
| Storage | Laravel Filesystem (local first, S3-compatible later) |

Deployment domains: `api.schooldomain.com` (API), `app.schooldomain.com` (portal frontend), `schooldomain.com` (public site).

## Architecture rules (enforce these when writing code)

**Backend layering** — keep business logic out of controllers:
```
Controller → Form Request (validation) → Action/Service (logic) → Repository/Eloquent → Model → MySQL
Response:  Model → API Resource → JSON
```
Use Form Requests for validation, API Resources for response shaping, Policies for authorization, Services/Actions for logic, Jobs for queued work, Events/Listeners for decoupling, Enums for fixed statuses. Use Repositories only where they add real value.

**Authorization is two-layer.** A permission (e.g. `marks.create`) is necessary but not sufficient. Ownership/scope must also be checked via Policies — e.g. a teacher may only enter marks for their *assigned* classes, subjects, and authorized exams. Never trust `user_id`, `role`, `permissions`, `price`, or payment amounts from the client.

**Money & finance.** Use `DECIMAL(15,2)`, never float. Wrap every payment operation (create payment → update invoice → recalc due → receipt → audit log) in a database transaction; roll back on any failure.

**Sensitive workflows are staged, not immediate.**
- Results: teacher marks → draft → review → approved → published → public. Never expose draft marks publicly.
- CMS content: draft → review → published → archived (schema should allow this even if V1 skips approval).

**Public result endpoint** must expose minimal student data — guard against enumeration/scraping.

**Caching:** cache stable data (school/website settings, navigation, published pages/notices/events, theme, academic config) and invalidate on CMS change. Never blindly cache changing financial data. Cache **plain arrays/scalars only — never Eloquent models or other objects**: `cache.serializable_classes` is `false`, so objects read back from the database/file/redis stores become `__PHP_Incomplete_Class`. The `array` store used by tests doesn't serialize and hides this, so cache tests should force a serializing store. Pattern: `SchoolSetting::current()` caches `getAttributes()` and rehydrates with `newFromBuilder()`.

**Queues** for email, SMS, large PDFs, bulk notifications, student import, report export, image processing.

## Module build order

Build **one module at a time** in dependency order; do not build all modules simultaneously. The canonical V1 order (Foundation first) lives in the architecture doc §64–67. Per-module build instructions and the completion tracker live in [docs/](docs/):

- [docs/README.md](docs/README.md) — how the docs are organized.
- [docs/PROGRESS.md](docs/PROGRESS.md) — **the live checklist. Mark a module complete here (and set the Status field in its module doc) only when backend + frontend + acceptance criteria are all done.**
- `docs/modules/NN-<module>.md` — one file per module with scope, backend tasks, frontend tasks, API endpoints, and acceptance criteria.

When starting or finishing a module, read that module's doc first, then update its status.

### Hook points every feature module plugs into

- **Routes:** inside the `['auth:api', 'token.type:access', 'active']` group in `backend/routes/api.php`, each gated with `permission:<name>`. Child records are listed/created under their parent (`/classes/{school_class}/sections`) and addressed directly afterwards (`/sections/{section}`); take the parent from the route, never the body.
- **List endpoints** paginate with `->paginate(PerPage::from(request()))` (`App\Support\PerPage`: default 25, max 100) — never pass raw `per_page` to `paginate()`.
- **Reference data is FK-protected:** any table referencing an academic year, class, section (and later subject…) declares `->constrained()->restrictOnDelete()`. A table storing both `class_id` and `section_id` must validate that the section belongs to that class. Deletes go through `App\Support\GuardedDelete::delete($model, $message)`, which turns the FK violation into a 409 — there's no list of dependents to maintain. Read the current year via `AcademicYear::current()` (cached); never store a copy of it elsewhere. Classes are a catalogue shared across years; the year is pinned at enrollment.
- **Person records ↔ logins:** a person's login is the `users` row whose polymorphic `personable` points at it (no `user_id` column on person tables). Register the model's alias (matching `User::PERSON_TYPES`) in the morph map in `AppServiceProvider`, expose it via `morphOne(User::class, 'personable')`, and create logins through `App\Actions\Person\CreatePersonAccountAction` with the role fixed server-side (validation: `App\Http\Requests\AccountRules`). `App\Rules\PersonLink` then validates links made from the Users screen.
- **Private files** (photos, documents): use the shared plumbing — `HasDocuments` on the model (polymorphic `documents` table + `fileFolder()`), `App\Actions\Files\*` actions, `StoreDocumentRequest` / `UploadPhotoRequest` (both `mimes:` and content-sniffed `mimetypes:`), and `DocumentResource`. Files live on the private `local` disk under random names and are exposed only as expiring signed links (`App\Support\SignedFileUrl::to()`) to routes in the `signed:relative` `files` group served by `PrivateFileController`. Never put them on the public disk. Frontend: `PhotoEditor`, `DocumentsPanel`, `FormField`, `DetailsList` in `components/`.
- **Who is in a section:** the **active enrollments of the current year** (`StudentEnrollment::active()` + `AcademicYear::current()`) — class, section and roll are never stored on `students`. Student lists that teachers can reach must apply `Student::visibleTo($user)`.
- **Teacher scope:** what a teacher may touch comes from `teaching_assignments` (teacher × section × class-subject) and `sections.class_teacher_id`. Section-based endpoints call `App\Support\TeacherScope::authorize($user, $section)` (403) or filter by `TeacherScope::sectionIds($user)` (null = not scoped) — never authorize against the Teacher role alone. Subject-level checks (marks) should narrow further to the teacher's own `teaching_assignments` rows.
- **Portals:** teacher/parent/student screens live under `app/{teacher,parent,student}/` (they can't open `/admin`); pass a `nav` to `PortalLayout` (use `exact: true` for the portal home link).
- **Generated numbers** (admission no., later receipts/invoices): `App\Support\Sequence::next($key)` inside the creating transaction, so failed saves don't leave gaps. Phone fields use the `App\Rules\PhoneNumber` rule.
- **Permissions:** add new names to `backend/config/permissions.php` (plus the role matrix) and re-run `RolePermissionSeeder` — never accept permission names from clients.
- **Tests:** authenticate with `$this->asUser($user)` from `backend/tests/TestCase.php`. Don't hand-roll token headers: it resets the scoped JWT manager + guards, without which a second user in the same test is served as the first.
- **Admin UI:** add a nav entry (with its `permission`) to `NAV` in `frontend/src/app/admin/layout.tsx`, and guard the page with `<RequireAuth permission="…">`. Reuse `errorMessage()` (`lib/errors.ts`), `labelOf()` for enum labels (`lib/options.ts`) and `<Pagination>` (`components/Pagination.tsx`); `features/classes/ClassesManager.tsx` is the reference CRUD screen. Fetch initial data in `useEffect` setting state only in promise callbacks (lint rule `react-hooks/set-state-in-effect`), and format date-only values with `formatDay` (not `new Date("YYYY-MM-DD")`, which is UTC).
- **Dashboard:** replace the module's `PendingWidget` in `backend/app/Services/Dashboard/DashboardService.php` with a real widget using the **same key**, scoped to the caller (ownership), and add a renderer in `frontend/src/features/dashboard/DashboardView.tsx`.

## Specialized agents

`.claude/agents/` defines subagents to delegate focused work. See [.claude/agents/README.md](.claude/agents/README.md) for the roster (backend, frontend, database/schema, API design, module orchestration, security review, QA). Prefer delegating a self-contained slice (e.g. "build the migrations + models for the Students module") to the matching agent.

## Commands

**Backend** (from `backend/`). First put Laragon's PHP 8.3 on PATH (the default `php` is 7.4 and won't run Laravel 13):
```bash
export PATH="/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:$PATH"
php artisan serve                 # API at http://localhost:8000
php artisan migrate               # MySQL db: school_management
php artisan test                  # PHPUnit 12
php artisan test --filter=Name    # single test
php artisan queue:work            # process the database queue
./vendor/bin/pint                 # format (Laravel Pint)
```
**Frontend** (from `frontend/`):
```bash
npm run dev                       # dev server at http://localhost:3000
npm run build                     # production build (type-checks)
npm run lint                      # ESLint
npm run format                    # Prettier write
```

## Reference

The architecture spec [school_management_system_full_architecture.md](school_management_system_full_architecture.md) is authoritative for scope, database tables (§46), API routes (§47), frontend structure (§48), backend structure (§49), security (§51–52), and the module lists (§65–66). Consult it before designing any module.
