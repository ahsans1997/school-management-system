# Module 25 — Notices

- **Status:** ⬜ Not started
- **Phase:** Phase 5 — Website CMS
- **Completed on:** —
- **Owner agent:** `module-builder`

> School notice management (spec §27) — admins publish notices once and they flow everywhere: the public website, the teacher/parent/student portals, and notifications ("enter once, use everywhere", spec §68).

## Scope

Includes (spec §27, §47, §57, §68): admin CRUD for notices with title, slug, description, publish date, category, attachment, featured flag, and a draft→review→published→archived status. Published notices are served read-only to the public site via `/api/public/*` (spec §47) and surface in the internal portals; draft/archived notices stay hidden publicly. CMS management under `/api/website/*`.

Excludes: the notification dispatch itself (channels, templates, queued sending) — wired in `37 — Notifications`; this module fires the domain event/flag that Module 37 consumes. Portal UI belongs to the respective portal modules; this module supplies the shared data source.

## Dependencies

- `04 — School Settings` (permission subject, site context). Notifications integration completed in `37 — Notifications`.

## Database (tables owned / referenced)

- `notices` — `id`, `title`, `slug` (unique), `description` (longtext), `publish_date` (date), `category` (string/enum), `attachment` (path, nullable), `featured` (boolean), `status` (enum: draft, review, published, archived), `published_at` (nullable), timestamps, soft deletes. Indexes on `slug` and `published_at` (spec §54); index on `status`, `featured`.
- References: read by public site and portals; triggers notifications in Module 37.

## Permissions

Spatie permission `website.notices` gates all CMS management (create/edit/publish/unpublish/delete). `website.view` for read-only CMS listing. Public endpoints require no auth. Admin/Principal hold `website.notices`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /website/notices | website.notices | paginated, all statuses |
| POST | /website/notices | website.notices | create; slug unique, attachment validated |
| GET | /website/notices/{id} | website.notices | show |
| PUT | /website/notices/{id} | website.notices | update |
| DELETE | /website/notices/{id} | website.notices | soft delete / archive |
| POST | /website/notices/{id}/publish | website.notices | → published; fires notice-published event; busts cache |
| GET | /public/notices | — | published only, paginated (featured first), cached |
| GET | /public/notices/{slug} | — | published only, by slug, cached |

## Backend tasks

- [ ] Migration + `Notice` model (unique `slug`, indexes on `slug`/`published_at`, status enum, soft deletes)
- [ ] Form Requests (validate unique slug, attachment MIME/size, `publish_date`; `authorize()`)
- [ ] Policy (record-level authorization on manage actions)
- [ ] Publish action setting status + `published_at` and dispatching a `NoticePublished` event (consumed by Module 37)
- [ ] API Resources: `NoticeResource` (CMS) and `PublicNoticeResource` (non-sensitive fields)
- [ ] Routes: `/website/notices` under JWT + `permission:website.notices`; `/public/notices` unauthenticated
- [ ] Cache published notice list + detail (spec §60); invalidate on publish/unpublish/update/delete

## Frontend tasks

- [ ] Admin pages under `admin/website/notices/` — paginated list, create/edit/view, publish toggle, featured toggle, attachment upload with validation
- [ ] Public listing/detail under `(public)/notices/` (Server Components)
- [ ] Portal notice widgets read the same published source
- [ ] Typed `noticeService` in `services/`; visible only to users holding `website.notices`

## Acceptance criteria

- [ ] A published notice appears on public `/notices` and in the portals
- [ ] A draft notice is hidden from the public API
- [ ] Attachment upload validated (MIME + max size)
- [ ] Featured flag works (featured notices surfaced first)
- [ ] Publishing triggers the notification event consumed by Module 37
- [ ] List endpoints are paginated (`?page&per_page`)
- [ ] Tests written and passing (publish gating, featured ordering, attachment validation, event dispatch)
- [ ] Security review passed (two-layer auth, upload validation, public field allow-list)

## Notes

Single source of truth for notices across the platform (spec §68) — public site, portals, and notifications all read the same `published` rows; never duplicate notice content per surface. Notification sending is queued in Module 37; this module only emits the event. Public payload excludes `status` and internal ids beyond slug.
