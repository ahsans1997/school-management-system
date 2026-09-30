# Module 23 — Homepage Builder

- **Status:** ⬜ Not started
- **Phase:** Phase 5 — Website CMS
- **Completed on:** —
- **Owner agent:** `module-builder`

> Configurable homepage composed of toggleable, reorderable sections (spec §25) — admins enable/disable, reorder, and edit each section's content and image; the public homepage renders enabled sections in order.

## Scope

Includes (spec §25, §47, §57, §60): management of the recommended homepage sections — Header, Slider, Welcome, Principal Message, Notice Board, Upcoming Events, Statistics, Facilities, Gallery, Teachers, Testimonials, Call To Action, Footer. Admins enable/disable sections, change their order, edit section content (JSON payload), and upload section images. Exposes CMS management under `/api/website/*` and a read-only public composition endpoint `/api/public/home` (spec §47) returning only enabled sections in `sort_order`.

Excludes: the underlying content those sections pull from — sliders (Module 24), notices (Module 25), events (Module 26), gallery (Module 27), teacher profiles (Module 27-adjacent) — which are referenced dynamically, not duplicated here. Theme styling (Module 33, deferred).

## Dependencies

- `22 — Website Pages` (shared CMS foundation, staging pattern, and cache conventions).

## Database (tables owned / referenced)

- `homepage_sections` — `id`, `key` (unique; e.g. `slider`, `welcome`, `principal_message`), `title`, `content` (JSON), `image` (path, nullable), `enabled` (boolean), `sort_order` (int), timestamps. Index on `enabled`, `sort_order`. Seeded with the full section set (disabled or default as appropriate).
- References: reads published rows from `sliders`, `notices`, `events`, `galleries` when composing dynamic sections.

## Permissions

Spatie permission `website.manage` gates all section management (enable/disable, reorder, edit). `website.view` for read-only CMS listing. Public `/api/public/home` requires no auth. Admin/Principal hold `website.manage`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /website/homepage/sections | website.manage | all sections incl. disabled |
| PUT | /website/homepage/sections/{key} | website.manage | edit content/title/image; image validated |
| POST | /website/homepage/sections/{key}/toggle | website.manage | enable/disable; busts cache |
| PUT | /website/homepage/sections/reorder | website.manage | set `sort_order` in bulk; busts cache |
| GET | /public/home | — | enabled sections only, in `sort_order`, cached |

## Backend tasks

- [ ] Migration + `HomepageSection` model (unique `key`, JSON cast on `content`) and section seeder
- [ ] Form Requests (validate `content` JSON shape per section, image MIME/size, reorder payload; `authorize()`)
- [ ] Reorder + toggle actions (bulk `sort_order` update in a transaction; no logic in controllers)
- [ ] Home composition service assembling enabled sections and hydrating dynamic ones from published content
- [ ] API Resources: `HomepageSectionResource` (CMS) and `PublicHomeResource` (non-sensitive, composed)
- [ ] Routes: `/website/homepage/*` under JWT + `permission:website.manage`; `/public/home` unauthenticated
- [ ] Cache the composed `/public/home` payload (spec §60); invalidate on toggle/reorder/edit and on referenced content changes

## Frontend tasks

- [ ] Admin builder under `admin/website/homepage/` — section list with enable toggles, drag-to-reorder, per-section content editor, image upload
- [ ] Public homepage under `(public)/page.tsx` rendering composed sections in order (Server Components)
- [ ] Typed `homepageService` in `services/`
- [ ] Visible only to users holding `website.manage`

## Acceptance criteria

- [ ] Sections can be toggled on/off and reordered; new order persists
- [ ] `/public/home` returns only enabled sections in `sort_order`
- [ ] A disabled section is hidden from the public payload
- [ ] Section image upload validated (MIME + max size)
- [ ] Tests written and passing (toggle, reorder ordering, disabled-section exclusion)
- [ ] Security review passed (two-layer auth, JSON content validation, upload validation, public field allow-list)

## Notes

Dynamic sections reference published content only — a disabled/draft slider or notice must never leak through the home composition. Composed public payload is cached and invalidated whenever any contributing content changes (spec §60).
