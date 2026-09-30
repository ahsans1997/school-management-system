# Module 22 — Website Pages

- **Status:** ⬜ Not started
- **Phase:** Phase 5 — Website CMS
- **Completed on:** —
- **Owner agent:** `module-builder`

> CMS-managed public website pages (spec §24) — admins author, stage, and publish pages (Home, About, Contact, …) that the public site renders by slug, with SEO metadata and a draft→published lifecycle.

## Scope

Includes (spec §24, §57, §58): admin CRUD for pages, unique-slug management, SEO metadata, featured-image upload, and a draft→review→published→archived staging workflow with publish/unpublish actions. Provides two route surfaces — authenticated CMS management under `/api/website/*` and read-only public access under `/api/public/*` (spec §47) that serves only published pages and non-sensitive fields. Seeds the default page set: Home, About, History, Mission, Principal Message, Administration, Teachers, Academics, Facilities, Admissions, Notices, Events, Gallery, Results, Downloads, Contact.

Excludes: homepage section layout (Module 23), sitemap/robots generation and global SEO defaults (deferred), navigation menus and theme (Modules 32/33, deferred). Approval gating is optional in V1 — the schema supports it (spec §57) but review is not enforced.

## Dependencies

- `04 — School Settings` (school identity/branding consumed by public pages; permission subject).

## Database (tables owned / referenced)

- `pages` — `id`, `title`, `slug` (unique), `body`/`content` (longtext, sanitized HTML), `featured_image` (path), SEO: `meta_title`, `meta_description`, `og_title`, `og_description`, `og_image`, `canonical_url`, `status` (enum: draft, review, published, archived), `published_at` (nullable), timestamps, soft deletes. Index on `slug`, `status`, `published_at`.
- References: none owned elsewhere; read by the public website and navigation.

## Permissions

Spatie permission `website.pages` gates all CMS management (create/edit/publish/unpublish/delete); `website.view` for read-only CMS listing. Public `/api/public/*` endpoints require no auth. Admin/Principal hold `website.pages`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /website/pages | website.pages | paginated, all statuses |
| POST | /website/pages | website.pages | create; slug unique, SEO fields |
| GET | /website/pages/{id} | website.pages | show |
| PUT | /website/pages/{id} | website.pages | update content + SEO |
| DELETE | /website/pages/{id} | website.pages | soft delete / archive |
| POST | /website/pages/{id}/publish | website.pages | → published, sets `published_at`, busts cache |
| POST | /website/pages/{id}/unpublish | website.pages | → draft/archived, busts cache |
| GET | /public/pages | — | published only, paginated, non-sensitive fields |
| GET | /public/pages/{slug} | — | published only, by slug, cached |

## Backend tasks

- [ ] Migration + `Page` model (unique `slug`, status enum, soft deletes) and default-page seeder
- [ ] Form Requests (validate unique slug, SEO lengths, featured-image MIME/size; `authorize()`)
- [ ] Policy (record-level authorization on manage actions)
- [ ] Publish/Unpublish actions (set status + `published_at`; no logic in controllers)
- [ ] API Resources: full `PageResource` (CMS) and slim `PublicPageResource` (non-sensitive fields only)
- [ ] Routes: `/website/pages` under JWT + `permission:website.pages`; `/public/pages` unauthenticated
- [ ] Cache published pages (spec §60) keyed by slug; invalidate on publish/unpublish/update/delete

## Frontend tasks

- [ ] Admin pages under `admin/website/pages/` — paginated list, create/edit/view, publish toggle, SEO panel, image upload with validation
- [ ] Public rendering under `(public)/` fetching by slug (Server Components)
- [ ] Typed `pageService` in `services/` (JWT-aware CMS methods + public read methods)
- [ ] Visible only to users holding `website.pages`

## Acceptance criteria

- [ ] A page is created with a unique slug (duplicate slug rejected)
- [ ] An unpublished (draft/review/archived) page is not served by the public API
- [ ] SEO fields (meta/OG/canonical) persist and round-trip through edit
- [ ] Public fetch by slug returns the published page with only non-sensitive fields
- [ ] List endpoints are paginated (`?page&per_page`)
- [ ] Featured-image upload validated (MIME + max size)
- [ ] Tests written and passing (slug uniqueness, publish gating, public exposure)
- [ ] Security review passed (two-layer auth, HTML sanitization, upload validation, public field allow-list)

## Notes

Body HTML must be sanitized on write to prevent stored XSS (spec §51). Public responses expose a strict field allow-list — never `status`, internal ids beyond slug, or timestamps beyond `published_at`. Caching invalidation on every content change is required (spec §60).
