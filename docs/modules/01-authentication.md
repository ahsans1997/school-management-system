# Module 01 — Authentication (JWT)

- **Status:** ✅ Complete
- **Phase:** Phase 1 — Foundation
- **Completed on:** 2026-09-30

> Stateless JWT authentication: login issuing access + refresh tokens, token refresh, logout, and the authenticated-user endpoint, plus the Bearer middleware that guards every protected route.

## Scope

Includes (spec §47 auth routes, §51 security): the `users` table and model wired as the JWT subject, login/logout/refresh/me endpoints, access-token + refresh-token flow, `Authorization: Bearer` middleware, secure password hashing (bcrypt/argon), and login throttling / rate limiting to resist brute force.

Excludes: role/permission definitions and gating (Module 02), user CRUD and admin management (Module 03), password-reset-by-admin (Module 03), self-service forgot-password email flow (deferred).

## Dependencies

`00 — Project Setup & Scaffolding`.

## Database (tables owned / referenced)

- `users` — id, name, email (unique), password (hashed), is_active, timestamps, soft deletes. Indexes: unique `email`. (Added `is_active` + `deleted_at` to the base migration.)
- No separate `refresh_tokens` table needed: refresh tokens are JWTs (`type=refresh`); logout/refresh invalidation uses the package's blacklist (backed by the database cache store).

## Permissions

None introduced. Authentication only establishes identity; authorization is layered on by Module 02.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| POST | /auth/login | public | throttled 5/min per email+IP; returns access + refresh token |
| POST | /auth/logout | authenticated (access) | blacklists the current token |
| POST | /auth/refresh | authenticated (refresh) | rotates: blacklists old refresh, issues a new pair |
| GET | /auth/me | authenticated (access) | returns the current user |

## Backend tasks

- [x] `users` migration + model implementing `JWTSubject`; `api` guard set to `jwt` (default guard `AUTH_GUARD=api`)
- [x] Form Requests: `LoginRequest` (validation + `authorize()`)
- [x] Actions/Services: `LoginAction`, `RefreshTokenAction`, `LogoutAction`, `TokenIssuer` (no logic in controllers)
- [x] `UserResource` for response shaping
- [x] Routes under `/api/auth` with `auth:api` on protected ones + `token.type:{access|refresh}` middleware
- [x] Rate limiter (`throttle:login`) on `/auth/login` (5/min per email+IP)
- [x] Token blacklist on logout; refresh rotation (old refresh token blacklisted) on refresh

## Frontend tasks

- [x] `(auth)/login` page with validation + error states (renders at `/login`)
- [x] Typed `authService` in `services/` (login, logout, refresh, me)
- [x] Token storage (`lib/tokenStore`) + Bearer header; silent refresh on 401 (`authedFetch`)
- [x] `AuthProvider` context + `RequireAuth` guard to redirect unauthenticated users to `/login`

## Acceptance criteria

- [x] Login returns valid access + refresh tokens
- [x] Protected route rejects missing/invalid token with 401
- [x] Refresh issues a new valid access token
- [x] Logout invalidates the token (subsequent use returns 401)
- [x] Repeated failed logins are throttled (429 on the 6th)
- [x] Tests written and passing (11 auth feature tests, 33 assertions; 14 total backend tests green)
- [x] Security review passed (bcrypt hashing via cast, secret only in git-ignored `.env`, short-lived 15-min access token, distinct token types enforced per route, no token in logs)

## Notes

Access tokens 15 min (`JWT_TTL`), refresh 7 days (`JWT_REFRESH_TTL=10080`) per spec §51. Never trust client-supplied `user_id`/`role` — credentials verified server-side, disabled accounts rejected. Forgot-password email flow deferred; admin-initiated reset handled in Module 03.

**Build notes (2026-09-30):**
- **Dual-token model.** Access + refresh are both JWTs distinguished by a `type` claim. `TokenIssuer` mints them via the `JWTAuth` manager (`fromUser`), NOT the guard's `login()`, to avoid mutating request auth state (that caused cross-request token bleed in feature tests). A `token.type:{access|refresh}` middleware stops a refresh token being used on access routes and vice versa.
- Package namespace is `PHPOpenSourceSaver` (case-sensitive PSR-4) — not `PhpOpenSourceSaver`.
- Verified end-to-end in the browser: login page → `POST /api/auth/login` (CORS OK) → tokens in `localStorage` → `GET /api/auth/me` returns the user. No console/CORS errors.
- Dev admin seeded in `DatabaseSeeder` (`admin@school.test` / `password`) for local testing — replace/extend when Module 02 adds roles.
- Frontend token storage uses `localStorage` (XSS surface); a future hardening step can move to httpOnly cookies.
- Op note: `php artisan serve` stopped via the harness can orphan the PHP process holding the port; kill by PID (`netstat -ano` → `taskkill //PID <pid> //F`) if a restart says the port is busy.
- **Fix (2026-10-04, found during Module 10):** concurrent 401s each called `/auth/refresh` with the same refresh token. Rotation blacklists it on first use, so the second call failed and the client wiped the new tokens, logging the user out. `authService.refresh()` is now single-flight, and `authedFetch` retries without refreshing if another caller already did, and doesn't clear tokens another tab just rotated.
