# Module 11 — Students

- **Status:** ✅ Complete
- **Phase:** Phase 2 — Academic Core
- **Completed on:** 2026-10-05
- **Owner agent:** `module-builder`

> The student master record (spec §9): profile, photo, documents, guardians, archive/restore and search. Class/section/roll membership is expressed through enrollments (Module 13).

## Scope

Includes:
- CRUD and a profile view, with archive via soft delete and restore.
- Generated identifiers, which can also be typed in when migrating existing records.
- A private photo and documents, served through expiring signed links.
- Search and a paginated list.
- The work carried over from Module 10: `student_guardians`, linking and unlinking, exactly one primary guardian per student, and parent scoping (the parent's "My children" widget).
- A real `student_stats` dashboard widget.

**Moved to Module 13** (see its "Carried over from Module 11" list), because each needs enrollment data (class, section, roll, enrollment status):
- **Transfer:** an enrollment status change.
- **Promote:** already planned for Module 13.
- **ID card:** needs class, section and roll.
- **Certificates:** need the transfer record, and results for testimonials.
- **Import/export:** real files carry class, section and roll, so an import should enroll students in the same pass.

Excludes: online admission intake (Module 31); attendance, marks and fees (later modules).

## Design decisions

- **Two identifiers, both generated:**
  - `admission_no` is the admission-register number, `YYYY-NNNN`, counted per admission year by `App\Support\Sequence`. That's a row-locked counter that runs inside the create transaction, so a failed save hands its number back.
  - `student_id` is the public-facing ID (ID card, public result lookup in Module 32). It's **8 random digits, not sequential**, so it can't be enumerated through the public result page.
  - Either can be typed in when migrating records; generation skips numbers already taken. Both stay unique across archived students too.
- **No duplicated state:** class, section, roll and session live on the enrollment (Module 13). Also dropped from the plan:
  - `students.roll`: the enrollment owns the roll.
  - `students.guardian_id`: the primary guardian is `student_guardians.is_primary`.

  `father_name` / `mother_name` stay as plain text, because certificates need them even when those parents aren't guardians.
- **Exactly one primary guardian:**
  - "At most one" is enforced by the database: a stored generated column `primary_marker` (`student_id` when primary, else NULL) with a unique index, the same trick as the current academic year.
  - "At least one" is kept by `App\Actions\Student\GuardianLinks`:
    - The first guardian linked becomes primary.
    - Making another primary moves the flag.
    - The primary can't be un-set, only replaced.
    - Unlinking the primary promotes the longest-linked remaining guardian.
  - Each change runs in a transaction holding the student's row lock.
- **Private files through signed links:**
  - Photos and documents live on the private `local` disk (random names), validated by extension and by sniffed content type. Photos: JPEG/PNG/WebP up to 2 MB. Documents: PDF or images up to 5 MB.
  - Responses carry **expiring signed links**: `App\Support\SignedFileUrl`, valid 1–2 h, stable within the hour so browsers can cache them, and signed host-independently. They point to `/api/files/...` routes behind `signed:relative`, so `<img>` and download links work without a bearer token. Only users who can view the student receive them.
  - Served files get `nosniff`, a sandbox CSP and `private` caching. Documents download under their original (sanitised) name.
  - Signatures are checked *before* route-model binding, so unsigned requests can't probe which records exist.
- **Archive, never delete:** `DELETE /students/{id}` soft-deletes. Archived students stay viewable (profile, photo, documents) but read-only: every write route returns 404 for them. They're restorable, and they drop out of lists, parent views and counts.
- **Permissions as seeded:** linking guardians and uploading files need `student.edit`. Receptionist (`student.create` only) can admit a student but not attach a photo or guardians; grant `student.edit` under Roles if the front desk should complete admissions. **Known gap:** Teachers hold `student.view` and can list every student until teacher assignments and enrollments exist. This is noted in Modules 12 and 13.

## Database

- `number_sequences` — `key` (unique), `next_value`.
- `students` — `student_id` (unique), `admission_no` (unique), `admission_date`, `name` (indexed), `dob`, `gender` (`App\Enums\Gender`), `blood_group` (`App\Enums\BloodGroup`, nullable), `father_name`, `mother_name`, `phone`, `address`, `previous_school` (nullable), `photo_path` (private disk), `deleted_at`, timestamps.
- `student_documents` — `student_id` (FK, cascade), `title`, `path`, `original_name`, `mime_type` (sniffed), `size`, `uploaded_by` (FK users, null on delete).
- `student_guardians` — `student_id` (FK, cascade), `guardian_id` (FK, **RESTRICT**: a linked guardian can't be deleted), `relationship` (`App\Enums\GuardianRelationship`), `is_primary`, `primary_marker` (generated, unique), unique (`student_id`,`guardian_id`).

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /students | student.view | paginated; `?search` (name, student ID, admission no., phone), `?archived=1`, `?gender`; rows carry `primary_guardian` + `photo_url` |
| POST | /students | student.create | generates `admission_no` / `student_id` when blank; `admission_date` defaults to today |
| GET | /students/{id} | student.view | profile incl. `guardians` (primary first) and `documents`; works for archived |
| PUT | /students/{id} | student.edit | update (404 when archived) |
| DELETE | /students/{id} | student.delete | archive (soft delete) |
| POST | /students/{id}/restore | student.delete | 409 if not archived |
| POST / DELETE | /students/{id}/photo | student.edit | upload (multipart `photo`) / remove |
| POST | /students/{id}/documents | student.edit | multipart `title` + `file` |
| DELETE | /students/{id}/documents/{docId} | student.edit | scoped to the student |
| POST | /students/{id}/guardians | student.edit | `{guardian_id, relationship, is_primary?}` |
| PUT | /students/{id}/guardians/{guardianId} | student.edit | `{relationship, is_primary?}`; `is_primary: false` on the primary → 422 |
| DELETE | /students/{id}/guardians/{guardianId} | student.edit | unlink; promotes a successor if it was primary |
| GET | /files/students/{id}/photo, /files/student-documents/{id} | signed link | no token; from `photo_url` / `download_url` |

Guardian side: `GET /guardians/{id}` now lists the guardian's `students`; the list carries `students_count`.

## Backend tasks

- [x] Migration + `Student` (soft deletes), `StudentDocument`, `student_guardians`, `number_sequences`; enums `Gender`, `BloodGroup`, `GuardianRelationship`
- [x] Form Requests (identifier format + uniqueness incl. archived, DOB in the past, enums, `PhoneNumber` rule, photo/document MIME + size)
- [x] Policy: permission middleware per route; ownership enforced where it exists today (parents through the children widget). Teacher scoping is deferred to Modules 12/13
- [x] `StudentNumberGenerator` + `Sequence`; create/update, photo, document and guardian-link actions
- [x] Signed private-file routes with hardened headers; signature checked before binding
- [x] Dashboard: real `student_stats` (headcount, admitted this month, archived, by gender) and parent `children` widgets
- → Moved to Module 13: transfer, promote, ID card/certificate PDFs, queued import/export

## Frontend tasks

- [x] `admin/students` list (search, archived toggle, photo thumbnails, primary guardian) + nav entry
- [x] `admin/students/new` and `[id]/edit` form with per-field server errors
- [x] `admin/students/[id]` profile: photo upload/replace/remove, guardians panel (find in register → relationship → link; re-label, make primary, unlink), documents panel (upload, download, delete), archive/restore, read-only banner when archived
- [x] Dashboard renderers for `student_stats` and `children`; guardians screen gains a Children column
- → Moved to Module 13: import/export UI, ID-card/certificate downloads

## Acceptance criteria

- [x] Creating a student generates a unique `admission_no` (collision-safe: row-locked counter, skips taken numbers, unique index)
- [x] Photo upload is validated (MIME + max size) and rejected otherwise (tested with real files whose content doesn't match the extension)
- [x] List supports search and is paginated (`?search&page&per_page`; the plan's `?q` is spelled `search` to match the Users and Guardians lists)
- [→ 13] Import and export run as queued jobs
- [x] Delete soft-deletes (archives), never hard-deletes
- [x] Carried from Module 10: many-to-many links, exactly one primary per student, a Parent sees only their own children
- [x] Tests written and passing (29 student tests across records, files and guardian links; 158 backend tests total)
- [x] Security review passed. The review agent stalled, so I reviewed it myself and fixed two findings, both now under test:
  - Uploaded filenames are stripped of control characters before going into the download header.
  - Signatures are checked before records are looked up, so unsigned requests can't probe which students exist.

## Notes

Canonical class/section/roll for a given year live on `student_enrollments` (Module 13). Uploads follow spec §44/§51 (MIME + size). Enter-once/use-everywhere: this record feeds attendance, results, fees and portals (spec §68). Signed file links act as short-lived bearer links: anyone holding one can open the file until it expires. That's the accepted trade-off for working `<img>` tags. Keep the window short (≤ 2 h) and never log full file URLs.

**Build notes (2026-10-05):**
- Verified end-to-end in the browser as the demo admin:
  - **Add student:** a bad phone showed its error under the phone field. Fixed, it saved as Nusrat Jahan with a generated student ID (8 random digits), `2026-0001`, and admission date = today, landing on the profile.
  - **Photo:** uploaded and rendered from the signed link (API on port 8001).
  - **Document:** a PDF uploaded and downloaded with `Content-Disposition: attachment; filename=birth-certificate.pdf` and the hardened headers. Unsigned → 403; another student's ID → no file; the raw private file via `/storage/...` → 403.
  - **Guardians:** linked Abdul Karim (Father), automatically primary, then Rahima Khatun (Mother), then made her primary. The list re-ordered and the badge moved.
  - **Parents (via the API):** karim.parent and rahima.parent each see only Nusrat in "My children"; the demo parent sees none. All three get 403 on `/students/1`.
  - **Archive:** the profile turned read-only (no edit, upload, link or delete controls) and Restore brought the controls back.
  - **Edit:** the edit form came prefilled, and saving returned to the profile.
  - **List and dashboard:** the list shows the photo thumbnail and the primary guardian. The dashboard tiles read 1 / 1 / 0, and the gender split shows Female 1.
  - No horizontal overflow at 375px on the list, profile, form, guardians and dashboard screens.
- Dev DB: student **Nusrat Jahan** (photo, one document, guardians Rahima Khatun [primary, mother] and Abdul Karim [father]).
- **Later change (Module 12):** `student_documents` was folded into the polymorphic `documents` table, shared with teachers and later admission applications, by a data migration. The photo/document actions, requests and file routes became shared ones (`App\Actions\Files\*`, `PrivateFileController`, `/api/files/documents/{id}`). The student API is unchanged apart from the document download URL path.
