# Module 28 — Teacher Website Profiles

- **Status:** ⬜ Not started
- **Phase:** Phase 5 — Website CMS
- **Completed on:** —
- **Owner agent:** `module-builder`

> Lets the school choose which teachers appear on the public website and in what category, presenting a read-only public profile that reuses existing teacher records without duplicating HR data.

## Scope

Includes (spec §30): flag which teachers are public, assign a display category (Principal, Head Teacher, Senior Teachers, Other Teachers), set display order, and maintain a public-facing short biography. The public profile shows photo, name, designation, department, qualification and short bio only. The public API returns only flagged teachers, ordered, categorised.

Excludes: teacher CRUD, HR fields, salary, private contact, documents and assignments (all owned by `12 — Teachers`). Per spec §68 (enter data once, use everywhere) this module stores **only** public-presentation attributes and reads the rest from the teacher record — it never copies name/designation/department/qualification.

## Dependencies

`12 — Teachers` (source of teacher identity + photo/qualification), `22 — Website Pages` (website.* permission foundation + public site shell).

## Database (tables owned / referenced)

- `teacher_public_profiles` — id, teacher_id (FK unique, cascade), is_public bool default false, category enum(principal, head_teacher, senior, other), public_order int default 0, public_bio text nullable, timestamps. Index (is_public, category, public_order). A satellite table (not columns on `teachers`) keeps website.manage writes isolated from the HR record.
- References: `teachers` — name, designation, department, qualification, photo (read-only projection for the public payload).

## Permissions

Introduces nothing new; uses `website.manage` (spec §6) to toggle visibility, categorise, order and edit the public bio. Managing public visibility must not grant HR edit rights. Public read requires no authentication.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /website/teacher-profiles | website.manage | paginated; teachers with their public flags |
| PUT | /website/teacher-profiles/{teacher} | website.manage | set is_public, category, order, bio; invalidates cache |
| POST | /website/teacher-profiles/reorder | website.manage | bulk set public_order within a category |
| GET | /public/teachers | — (public) | cached; only is_public=true; minimal fields |

## Backend tasks

- [ ] Migration + model for `teacher_public_profiles` (unique teacher_id, indexes)
- [ ] Form Requests (validation + `authorize()` on website.manage) for update + reorder
- [ ] Action/Service to upsert the profile and invalidate the public cache (no logic in controllers)
- [ ] `PublicTeacherResource` — projects photo, name, designation, department, qualification, bio ONLY
- [ ] Cache read-through for `/public/teachers` (spec §60); bust on any profile change
- [ ] Routes: `/website/*` under JWT + `permission:website.manage`; `/public/teachers` unauthenticated

## Frontend tasks

- [ ] `admin/website/teachers` — list all teachers with public toggle, category select, drag-order, bio editor
- [ ] `(public)/` administration/teachers page grouped by category
- [ ] Typed `teacherProfileService` (list, update, reorder) + public fetch method
- [ ] Visible only where website.manage

## Acceptance criteria

- [ ] Only flagged (is_public=true) teachers appear on `/public/teachers`
- [ ] Category grouping and display order are respected in the public payload
- [ ] Public bio is editable without touching the underlying HR/teacher record
- [ ] HR-only fields (salary, private phone, documents) never appear in the public response
- [ ] Toggling visibility invalidates the public cache
- [ ] Tests written and passing (visibility filter, ordering, no HR leakage, cache bust)
- [ ] Security review passed (public projection minimal, website.manage gated)

## Notes

Satellite-table design keeps the HR record clean and the "enter data once" principle intact (spec §68). Public endpoint is a good caching candidate (spec §60). If the school prefers, `is_public`/`public_order`/`category`/`public_bio` could live as columns on `teachers` — the satellite table is chosen to avoid coupling CMS writes to HR audit history.
