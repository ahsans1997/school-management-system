# Module 27 — Gallery

- **Status:** ⬜ Not started
- **Phase:** Phase 5 — Website CMS
- **Completed on:** —
- **Owner agent:** `module-builder`

> Photo gallery management (spec §29) — admins organize images into albums/categories (Annual Sports, Cultural Program, …), upload multiple images per album, order and feature them, and publish albums to the public site.

## Scope

Includes (spec §29, §47, §57, §60): album (category) CRUD with cover image and status, plus per-album image management — multiple image upload, ordering, a featured flag, and captions. Albums follow a draft→review→published→archived status; published albums and their images are served read-only via `/api/public/*` (spec §47). CMS management under `/api/website/*`.

Excludes: video galleries, per-image access control, and image CDN/resizing pipeline (resizing noted for later, spec §59). The homepage Gallery section composition belongs to Module 23; this module supplies the data.

## Dependencies

- `04 — School Settings` (permission subject, site context).

## Database (tables owned / referenced)

- `galleries` — `id`, `title`, `slug` (unique, optional), `cover` (path, nullable), `status` (enum: draft, review, published, archived), `published_at` (nullable), `sort_order` (int), timestamps, soft deletes. Index on `status`, `published_at`.
- `gallery_images` — `id`, `gallery_id` (FK → `galleries`, cascade), `image` (path, required), `caption` (nullable), `sort_order` (int), `featured` (boolean), timestamps. Index on `gallery_id`, `sort_order`.
- References: read by public site and Module 23 homepage composition.

## Permissions

Spatie permission `website.gallery` gates all CMS management (album + image CRUD, publish, reorder). `website.view` for read-only CMS listing. Public endpoints require no auth. Admin/Principal hold `website.gallery`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /website/galleries | website.gallery | paginated, all statuses |
| POST | /website/galleries | website.gallery | create album; cover validated |
| PUT | /website/galleries/{id} | website.gallery | update album |
| DELETE | /website/galleries/{id} | website.gallery | soft delete album + images |
| POST | /website/galleries/{id}/publish | website.gallery | → published; busts cache |
| POST | /website/galleries/{id}/images | website.gallery | bulk image upload; each validated |
| PUT | /website/galleries/{id}/images/reorder | website.gallery | bulk `sort_order`; busts cache |
| DELETE | /website/galleries/{id}/images/{imageId} | website.gallery | remove image |
| GET | /public/gallery | — | published albums only, paginated, cached |
| GET | /public/gallery/{id} | — | published album + images in order, cached |

## Backend tasks

- [ ] Migrations + `Gallery` and `GalleryImage` models (FK cascade, status enum, soft deletes)
- [ ] Form Requests (validate album fields, bulk image MIME/size per file, reorder payload; `authorize()`)
- [ ] Policy (record-level authorization on manage actions)
- [ ] Bulk-upload + reorder actions (store files, set `sort_order`; no logic in controllers)
- [ ] API Resources: CMS resources and `PublicGalleryResource` / `PublicGalleryImageResource` (non-sensitive)
- [ ] Routes: `/website/galleries` under JWT + `permission:website.gallery`; `/public/gallery` unauthenticated
- [ ] Cache published albums + images (spec §60); invalidate on publish/unpublish/image change/reorder/delete

## Frontend tasks

- [ ] Admin pages under `admin/website/gallery/` — album list (paginated), album editor with multi-file upload, drag-to-reorder, featured toggle, publish toggle
- [ ] Public gallery under `(public)/gallery/` — album grid and image lightbox (Server Components, lazy-loaded images)
- [ ] Typed `galleryService` in `services/`
- [ ] Visible only to users holding `website.gallery`

## Acceptance criteria

- [ ] An album can hold multiple images uploaded in one request
- [ ] Image ordering (`sort_order`) is respected in the public response
- [ ] An unpublished album is hidden from the public API
- [ ] Bulk upload is validated per file (MIME + max size); invalid files rejected
- [ ] Featured images are flagged and surfaceable
- [ ] List endpoints are paginated (`?page&per_page`)
- [ ] Tests written and passing (multi-upload, ordering, publish gating, validation)
- [ ] Security review passed (two-layer auth, per-file upload validation, public allow-list)

## Notes

Bulk upload validates every file independently and enforces a max count per request to bound payload size (spec §51). Public payload excludes album `status` and internal timestamps; image resizing/CDN is deferred (spec §59). Cache invalidation covers both album and image mutations (spec §60).
