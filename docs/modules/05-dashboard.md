# Module 05 — Dashboard

- **Status:** ✅ Complete (re-verify widgets as their source modules land)
- **Phase:** Phase 1 — Foundation
- **Completed on:** 2026-09-30

> A single role-aware dashboard endpoint that returns a payload shaped to the caller's role, aggregating counts and recent activity from source modules as they come online.

## Scope

Includes (spec §7): one `GET /dashboard` endpoint shaped to the caller's audience (admin / staff / teacher / parent / student), the admin area shell with permission-filtered navigation, the teacher/parent/student portal shells, and role-based routing after login.

Excludes: the source data itself — this module only reads and aggregates. Widgets whose source module is not yet built are returned as labelled **pending** placeholders and light up as those modules land.

## Dependencies

`03 — Users` (and reads from academic/finance/website modules as they are completed).

## Database (tables owned / referenced)

- Owns no tables. Live today: `users`, Spatie `roles`/`model_has_roles`, `school_settings`. Future widgets read `students`, `attendances`, `fee_invoices`, `payments`, `notices`, etc. Money read as `decimal(15,2)`; never recompute money as float.

## Permissions

No new permissions. Access requires authentication. Each widget declares its **audiences** and an optional **permission** (two layers), and future widgets add ownership scoping (parent→own children, student→self, teacher→assigned classes).

## Audiences

`App\Enums\DashboardAudience::forUser()` decides it server-side — precedence **admin** (Admin, Principal) > **teacher** > **staff** (any other role, or none) > **parent** > **student**. The login/`me` payload carries `audience`, so the frontend never re-implements the rule.

| Audience | Lands on | Chrome |
|---|---|---|
| admin, staff | `/admin/dashboard` | admin shell + nav filtered by permission |
| teacher | `/teacher` | portal shell |
| parent | `/parent` | portal shell |
| student | `/student` | portal shell |

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /dashboard | authenticated | `{audience, generated_at, widgets[]}`; each widget `{key, title, status: ready\|pending, module?, data}` |

## Widgets

**Live:** `profile` (caller's own account — everyone), `school` (public identity from cached settings — everyone), `user_stats` (total/active/disabled + by-role; admin & staff with `user.view`), `recent_users` (newest 5, roles eager-loaded; admin & staff with `user.view`).

**Pending (registry in `DashboardService`):** admin/staff — `student_stats`, `teacher_stats`, `attendance_today`, `upcoming_exams`, `pending_fees`, `monthly_finance`, `recent_admissions` (each gated by its data's permission); teacher — `todays_classes`, `attendance_tasks`, `assigned_subjects`, `pending_marks`; parent — `children`, `child_attendance`, `child_results`, `child_fees`; student — `my_attendance`, `my_results`, `my_routine`, `my_fees`; shared — `homework`, `recent_notices`, `upcoming_events`.

> **To light up a widget** when its module lands: add a `Widget` subclass in `app/Services/Dashboard/Widgets/`, replace the matching `PendingWidget` in `DashboardService::widgets()` **keeping the same key**, scope `data()` to the caller (ownership), add a renderer in `features/dashboard/DashboardView.tsx` (unknown keys fall back to a generic card), and add a test.

## Backend tasks

- [x] Per-widget classes + `DashboardService` registry (no logic in controllers)
- [x] Two-layer visibility (audience + permission); ownership scoping hook for future widgets
- [x] `DashboardResource` — discriminated payload (`audience` + `widgets`)
- [x] Graceful degradation: unbuilt modules are `pending` placeholders that touch no data
- [x] Caching: reuses the cached settings singleton; user counts are cheap COUNTs (no dues are cached)
- [x] Route under `/api` with JWT + `active` middleware

## Frontend tasks

- [x] `admin/dashboard` + `teacher/`, `parent/`, `student/` pages; `/admin` redirects to the dashboard
- [x] Typed `dashboardService`
- [x] Widget components: KPI row of stat tiles, cards, tables, pending list, generic fallback
- [x] Role-appropriate visibility: area guard (`RequireAuth audiences`), permission-filtered admin nav, post-login redirect by audience

## Acceptance criteria

- [x] Endpoint returns a role-appropriate payload for each role
- [x] No cross-role data leakage (student/teacher payloads contain no other users' data)
- [x] Numbers match their source tables (soft-deleted users excluded)
- [x] Widgets for not-yet-built modules render empty without error
- [x] Tests written and passing (12 dashboard tests, 89 assertions; 62 backend tests total)
- [x] Security review passed (two-layer widget gating, area guard is UX only — API scopes every call)

## Notes

A single endpoint serves every role, so the audience + permission layers are essential (spec §52). Recent-activity lists eager-load and cap at 5 (spec §59). This module's acceptance re-verifies as source modules add real widgets.

**Build notes (2026-09-30):**
- **Test harness:** a test making requests as two different users was served as the first user — the JWT manager (`tymon.jwt`, a *scoped* binding) memoizes the parsed token and the guard caches the user, and feature tests reuse one app. Fixed with a shared `asUser()` on the base `TestCase` that calls `forgetScopedInstances()` + `forgetGuards()`; the three per-class copies in Module 02–04 tests were removed. Production is unaffected (one request per process; Octane flushes scoped instances).
- **Visual design** (per the data-viz guidance): user totals are a KPI row of stat tiles, not a chart (no history → no fake delta/sparkline); the 11-role breakdown is a table (> 7 classes) with tabular figures; no categorical color, so no palette to validate; text stays in text tokens.
- **Demo accounts** moved into `DatabaseSeeder` and seeded **only when `APP_ENV=local`** (known passwords must never reach production). Production will need a first-admin bootstrap command — track for deployment.
- Verified end-to-end in the browser: the real login form routed admin → `/admin/dashboard` and teacher → `/teacher`; a teacher opening `/admin/dashboard`, `/student` or `/parent` was sent back to `/teacher`; admin KPIs showed 6/6/0 matching the DB; the accountant saw only the Dashboard nav link and fee/finance/student placeholders; the student saw only their own data. Layout checked by geometry at 1280 px and in a genuine 375 px iframe (tiles stack, nav becomes a scrollable row, no horizontal overflow) because screenshots time out while the app window is in the background.
