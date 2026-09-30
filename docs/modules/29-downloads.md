# Module 29 — Download Center

- **Status:** ⬜ Not started
- **Phase:** Phase 5 — Website CMS
- **Completed on:** —
- **Owner agent:** `module-builder`

> Admin uploads downloadable documents (forms, calendars, routines, syllabi, prospectus) which the public website lists automatically once published, with MIME/size-validated uploads and optional download counting.

## Scope

Includes (spec §31): upload, categorise, order, publish/unpublish and edit downloadable files. Examples: admission form, academic calendar, exam routine, holiday list, syllabus, prospectus, notice PDF. The public site auto-lists published downloads; unpublished items stay hidden. Uploads are MIME- and size-validated; download count is optional.

Excludes: gallery images (module 26), notice attachments (Notices module), authenticated document stores. No approval workflow beyond draft/published (spec §57 allows extending later).

## Dependencies

`04 — School Settings` (upload disk/storage config, allowed types).

## Database (tables owned / referenced)

- `downloads` — id, title, file_path, file_name, file_mime, file_size (bytes), category enum(form, calendar, routine, holiday, syllabus, prospectus, notice, other), description text nullable, status enum(draft, published) default draft, sort_order int default 0, download_count unsigned int default 0, uploaded_by (FK users), timestamps, soft deletes. Index (status, category, sort_order).

## Permissions

Uses `website.manage` (spec §6) for upload/edit/delete/publish. Public list + file fetch require no authentication.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /website/downloads | website.manage | paginated; all statuses |
| POST | /website/downloads | website.manage | upload file (MIME + size validated) |
| PUT | /website/downloads/{id} | website.manage | edit metadata / replace file; invalidate cache |
| DELETE | /website/downloads/{id} | website.manage | soft delete; invalidate cache |
| GET | /public/downloads | — (public) | cached; published only; paginated |
| GET | /public/downloads/{id}/file | — (public) | streams file; increments download_count |

## Backend tasks

- [ ] Migration + model (indexes, soft deletes)
- [ ] Form Requests (validation + `authorize()`); enforce allowed MIME whitelist + max upload size (spec §51)
- [ ] Action/Service: store upload, publish toggle, invalidate public cache (no logic in controllers)
- [ ] `DownloadResource` (public variant omits uploaded_by / internal fields)
- [ ] Cache read-through for `/public/downloads` (spec §60); bust on create/update/delete/publish
- [ ] Streaming download endpoint that increments count without blocking the response
- [ ] Routes: `/website/*` JWT + `permission:website.manage`; `/public/*` unauthenticated

## Frontend tasks

- [ ] `admin/website/downloads` — upload form, category, order, publish toggle, list (paginated)
- [ ] `(public)/downloads` — grouped by category with file links
- [ ] Typed `downloadService` (list, upload, update, delete) + public fetch
- [ ] Client-side type/size hints and upload error states

## Acceptance criteria

- [ ] An uploaded, validated, published file appears on `/public/downloads`
- [ ] Draft/unpublished files are hidden from the public list and file endpoint
- [ ] Invalid MIME or oversize uploads are rejected server-side
- [ ] Download count increments on public fetch (optional feature works)
- [ ] Creating/updating/deleting a download invalidates the public cache
- [ ] Tests written and passing (upload validation, publish visibility, cache bust, count)
- [ ] Security review passed (MIME whitelist, size cap, no path traversal, website.manage gated)

## Notes

Store files on the configured Laravel disk (spec §44) with generated names to avoid collisions and path traversal. Public list is a caching candidate (spec §60). Download counting is intentionally best-effort (increment on stream) and not a financial figure, so eventual consistency is acceptable.
