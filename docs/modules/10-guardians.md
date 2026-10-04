# Module 10 — Guardians

- **Status:** ✅ Complete
- **Phase:** Phase 2 — Academic Core
- **Completed on:** 2026-10-04
- **Owner agent:** `module-builder`

> Manages guardians/parents (spec §20), each with an optional Parent-portal login. Links to students (and parent scoping to their own children) arrive with Students in Module 11.

## Scope

Includes: CRUD for guardians with search; optionally creating a Parent login when the guardian is created, or later.

Moved to Module 11, as the plan already allowed ("student links are wired when Module 11 exists"): the `student_guardians` pivot (relationship + `is_primary`, one primary per student), the link/unlink endpoints and UI, and the "a Parent login sees only their linked children" ownership policy. All three need the `students` table.

Excludes: parent portal screens (Module 33), messaging/notifications (later modules).

## Design decisions

- **The login link lives in one place.** Module 03 already gave `users` a polymorphic person link (`personable_type` / `personable_id`) for exactly this. Adding `guardians.user_id` as well would create two links that could disagree. So a guardian's login is the user whose `personable` points at it (`Guardian::account()` is a `morphOne`), and `'guardian' => Guardian::class` is registered in the morph map (`AppServiceProvider`).
- **The Users screen's person link is now validated.** The new `App\Rules\PersonLink` rule checks that the record exists and doesn't already have a login. It applies to each person type once its module registers a morph alias; types not built yet are still stored as given, as in Module 03.
- **Creating a login needs `guardian.edit`,** including inline on `POST /guardians` (checked in `StoreGuardianRequest::authorize`). Creating credentials is more privileged than recording a contact, so a Receptionist (who has `guardian.create` only) can add guardians but not logins. The role is always `Parent`, set server-side; an `account` payload with extra keys (e.g. `roles`) is rejected.
- **Guardian and login are created together or not at all** (one transaction). The contact `email` and the login email are separate fields (many parents have no email; the UI prefills one from the other). The login's display name follows the guardian's name on update.
- **Delete:** refused (409) while the guardian has a login, since the morph link has no FK and the login would be orphaned. Delete the account under Users first. Once `student_guardians` exists, linked students block deletion via FK RESTRICT (`GuardedDelete`).
- **Deferred:** guardian photo (not in spec §20; add with the student photo-upload pattern if wanted). Phone isn't unique, because parents often share a number; the screen tells staff to search by phone first.

## Database

- `guardians` — `id`, `name` (≤ 100, indexed), `phone` (≤ 20, indexed), `email` (nullable, contact), `occupation` (nullable), `address` (nullable, ≤ 500), timestamps.
- Login: `users.personable_type = 'guardian'`, `users.personable_id = guardians.id` (no new column).

## Permissions

`guardian.view|create|edit|delete` (seeded in Module 02). Admin holds all; Principal `.view`; Receptionist `.view` + `.create`. Teachers and Parents get 403 on these admin endpoints.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /guardians | guardian.view | paginated, ordered by name; `?search` matches name, phone or email; each row carries `account` (id, email, is_active) or null |
| POST | /guardians | guardian.create (+ guardian.edit with `account`) | optional `account: {email, password}` → Parent login in the same transaction |
| GET | /guardians/{id} | guardian.view | show with `account` |
| PUT | /guardians/{id} | guardian.edit | update; renames the login |
| DELETE | /guardians/{id} | guardian.delete | 409 while it has a login (later also while linked to students) |
| POST | /guardians/{id}/account | guardian.edit | add a Parent login later; 409 if it already has one |

## Backend tasks

- [x] Migration + `Guardian` model (`account()` morphOne), morph-map alias
- [x] Form Requests (phone format, email, lengths; optional `account`; authorize rule for logins)
- [x] Actions: create (transactional, optional login), update (syncs login name), delete (guards), create login (reuses `CreateUserAction`, role fixed to Parent)
- [x] `PersonLink` validation on the Users API
- [x] API Resource + routes with JWT + `permission:`
- [x] Dashboard: the parent "My children" placeholder now points at Module 11
- [x] Local demo data: the seeded `parent@school.test` login is now linked to a "Demo Parent" guardian
- → Moved to Module 11: `student_guardians`, link/unlink, single primary per student, Parent ownership policy

## Frontend tasks

- [x] Admin page `admin/guardians/` (search, create/edit form, optional "also create a parent-portal login", "Create login" for existing guardians, delete) + nav entry
- [x] Typed `guardianService` + `types/guardian.ts`
- [x] Validation + error states (phone format, duplicate login email, 409s); login controls only with `guardian.edit`
- → Moved to Module 11: link-to-student UI (relationship, primary toggle)

## Acceptance criteria

- [→ 11] A guardian can be linked to multiple students; a student to multiple guardians
- [→ 11] Exactly one primary guardian can be designated per student
- [→ 11] A Parent login sees only their linked children (ownership policy enforced). *Groundwork done here: each Parent login resolves to its guardian record, and Parents are refused the guardian register (403).*
- [x] List endpoint is paginated (`?page&per_page`) and searchable
- [x] Tests written and passing (13 guardian tests; 129 backend tests total)
- [x] Security review passed (permission gating; login creation needs `guardian.edit` and can't pick its role; credentials hashed server-side; person links validated; no orphaned logins)

## Notes

Login account creation reuses Module 03's `CreateUserAction`. Password entry and hashing stay server-side.

**Build notes (2026-10-04):**
- **Shared fix (Module 01's frontend auth):** when the access token had expired and a screen fired two requests at once, both tried to refresh with the same refresh token. The server rotates that token on use, so the second refresh failed and the client wiped the brand-new tokens, logging the user out. You'd hit it after ~15 idle minutes on any screen that loads two things at once (the class-subjects screen does). `authService.refresh()` is now single-flight. A request that 401s after another caller already refreshed just retries. A failed refresh no longer clears tokens that another tab has just rotated. Reproduced before the fix ("Unauthenticated.", tokens wiped) and verified after: 4 parallel 401s, one refresh, all retries 200.
- Verified end-to-end in the browser as the demo admin:
  - Created Abdul Karim with a parent login in one step. Rahima Begum and Mina Das were added without logins; an invalid phone ("call me") was refused with the custom message.
  - Search by "01811" found Rahima, and "Create login" gave her a login. Renaming her to Rahima Khatun renamed the login too.
  - Deleting Abdul (has a login) was refused with the 409 message; Mina was deleted. No overflow at 375px.
  - Via the API: both new logins hold only the Parent role, are linked as `guardian`, and sign in to the `parent` audience. A Parent gets 403 on `/guardians`.
- Dev DB guardians: **Abdul Karim** (login `karim.parent@school.test`), **Rahima Khatun** (login `rahima.parent@school.test`), **Demo Parent** (login `parent@school.test`). All demo logins use the documented demo password.
