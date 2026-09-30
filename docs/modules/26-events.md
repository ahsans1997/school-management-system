# Module 26 — Events

- **Status:** ⬜ Not started
- **Phase:** Phase 5 — Website CMS
- **Completed on:** —
- **Owner agent:** `module-builder`

> School event management (spec §28) — admins publish events (sports, cultural programs, meetings) that appear on the public events listing and as upcoming events on the homepage.

## Scope

Includes (spec §28, §47, §57, §60): admin CRUD for events with title, date, time, location, description, image, registration URL, and a draft→review→published→archived status. Published events are served read-only to the public site via `/api/public/*` (spec §47); upcoming events (future date) surface on the homepage, and past events are treated as archived. CMS management under `/api/website/*`.

Excludes: registration handling/ticketing (only an external `registration_url` is stored), calendar sync, and event reminders (dispatched by `37 — Notifications`). The homepage "Upcoming Events" section composition belongs to Module 23; this module supplies the data.

## Dependencies

- `04 — School Settings` (permission subject, site context).

## Database (tables owned / referenced)

- `events` — `id`, `title`, `date` (date), `time` (time, nullable), `location` (string, nullable), `description` (longtext), `image` (path, nullable), `registration_url` (nullable), `status` (enum: draft, review, published, archived), `published_at` (nullable), timestamps, soft deletes. Index on `date`, `status`, `published_at`.
- References: read by public site and Module 23 homepage composition.

## Permissions

Spatie permission `website.events` gates all CMS management (create/edit/publish/unpublish/delete). `website.view` for read-only CMS listing. Public endpoints require no auth. Admin/Principal hold `website.events`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /website/events | website.events | paginated, all statuses |
| POST | /website/events | website.events | create; image validated |
| GET | /website/events/{id} | website.events | show |
| PUT | /website/events/{id} | website.events | update |
| DELETE | /website/events/{id} | website.events | soft delete / archive |
| POST | /website/events/{id}/publish | website.events | → published, sets `published_at`; busts cache |
| GET | /public/events | — | published only, paginated; `?upcoming=1` filter; cached |
| GET | /public/events/{id} | — | published only; cached |

## Backend tasks

- [ ] Migration + `Event` model (status enum, index on `date`, soft deletes)
- [ ] Form Requests (validate `date`/`time`, image MIME/size, `registration_url` format; `authorize()`)
- [ ] Policy (record-level authorization on manage actions)
- [ ] Publish action (status + `published_at`); upcoming/past split derived from `date` at query time
- [ ] API Resources: `EventResource` (CMS) and `PublicEventResource` (non-sensitive fields)
- [ ] Routes: `/website/events` under JWT + `permission:website.events`; `/public/events` unauthenticated
- [ ] Cache published + upcoming event lists (spec §60); invalidate on publish/unpublish/update/delete

## Frontend tasks

- [ ] Admin pages under `admin/website/events/` — paginated list, create/edit/view, publish toggle, image upload with validation
- [ ] Public listing/detail under `(public)/events/` with upcoming-first ordering (Server Components)
- [ ] Typed `eventService` in `services/`
- [ ] Visible only to users holding `website.events`

## Acceptance criteria

- [ ] Upcoming events (future `date`) are surfaced on the homepage and public listing
- [ ] Past events are archived/excluded from the upcoming view
- [ ] The public API returns only published events
- [ ] Event image upload validated (MIME + max size)
- [ ] List endpoints are paginated (`?page&per_page`)
- [ ] Tests written and passing (publish gating, upcoming/past split, image validation)
- [ ] Security review passed (two-layer auth, upload validation, `registration_url` sanitization, public allow-list)

## Notes

`registration_url` is validated (http/https only) to avoid injecting malicious links. Upcoming vs. past is computed from `date` against the current day, not a stored flag, so it stays correct without a scheduler; an optional scheduled job may auto-archive long-past events. Public payload excludes `status` and internal timestamps beyond `published_at` (spec §60).
