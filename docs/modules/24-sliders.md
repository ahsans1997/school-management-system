# Module 24 — Sliders

- **Status:** ⬜ Not started
- **Phase:** Phase 5 — Website CMS
- **Completed on:** —
- **Owner agent:** `module-builder`

> Homepage/hero slider management (spec §26) — admins create image slides with title, caption, and call-to-action button; active slides appear automatically on the public website in sort order.

## Scope

Includes (spec §26, §47, §60): CRUD for sliders with title, description, image, button text, button URL, status (active/inactive), and sort order. Active sliders are served read-only to the public site via `/api/public/*` (spec §47) in ascending `sort_order`; inactive slides are hidden. CMS management lives under `/api/website/*`.

Excludes: where sliders render (the homepage Slider section is composed by Module 23), theme styling (Module 33, deferred). Sliders use a simple active/inactive flag rather than the full draft→archived workflow, but the field maps onto the same published-vs-not semantics.

## Dependencies

- `04 — School Settings` (permission subject and site branding context).

## Database (tables owned / referenced)

- `sliders` — `id`, `title`, `description` (nullable), `image` (path, required), `button_text` (nullable), `button_url` (nullable), `status` (enum: active, inactive), `sort_order` (int), timestamps, soft deletes. Index on `status`, `sort_order`.
- References: consumed by Module 23 homepage composition and the public site.

## Permissions

Spatie permission `website.slider` gates all CMS management (create/edit/delete/toggle/reorder). `website.view` for read-only CMS listing. Public endpoints require no auth. Admin/Principal hold `website.slider`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /website/sliders | website.slider | paginated, all statuses |
| POST | /website/sliders | website.slider | create; image required + validated |
| GET | /website/sliders/{id} | website.slider | show |
| PUT | /website/sliders/{id} | website.slider | update; image optional |
| DELETE | /website/sliders/{id} | website.slider | soft delete; busts cache |
| PUT | /website/sliders/reorder | website.slider | bulk `sort_order`; busts cache |
| GET | /public/sliders | — | active only, in `sort_order`, cached |

## Backend tasks

- [ ] Migration + `Slider` model (status enum, soft deletes)
- [ ] Form Requests (validate image MIME/size, `button_url` format, reorder payload; `authorize()`)
- [ ] Policy (record-level authorization on manage actions)
- [ ] Reorder action (bulk `sort_order` in a transaction; no logic in controllers)
- [ ] API Resources: `SliderResource` (CMS) and `PublicSliderResource` (non-sensitive fields)
- [ ] Routes: `/website/sliders` under JWT + `permission:website.slider`; `/public/sliders` unauthenticated
- [ ] Cache active-slider list (spec §60); invalidate on create/update/delete/toggle/reorder

## Frontend tasks

- [ ] Admin pages under `admin/website/sliders/` — paginated list, create/edit, image upload with validation, drag-to-reorder, active toggle
- [ ] Public rendering via homepage Slider section (Server Components)
- [ ] Typed `sliderService` in `services/`
- [ ] Visible only to users holding `website.slider`

## Acceptance criteria

- [ ] Active sliders are returned by the public API in ascending `sort_order`
- [ ] An inactive slider is hidden from the public API
- [ ] Slider image upload is validated (MIME + max size); creation without a valid image is rejected
- [ ] List endpoint is paginated (`?page&per_page`)
- [ ] Tests written and passing (active ordering, inactive exclusion, image validation)
- [ ] Security review passed (two-layer auth, upload validation, URL/field sanitization, public allow-list)

## Notes

`button_url` must be validated/sanitized (allow only http/https or internal paths) to avoid injecting malicious links onto the public site. Public payload excludes `status` and internal timestamps. Cache invalidation on any change keeps the public site current (spec §60).
