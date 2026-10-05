# Module 12 — Teachers

- **Status:** 🔵 In review: built, tested and verified through the live API. The admin UI builds, lints and type-checks but still needs a browser click-through (see Build notes).
- **Phase:** Phase 2 — Academic Core
- **Completed on:** —
- **Owner agent:** `module-builder`

> The teacher master record (spec §11): profile, photo, documents, salary, an optional portal login, and the teaching assignments that later scope routine, attendance and marks entry (spec §52).

## Scope

Includes:
- CRUD for teachers with the spec §11 fields, plus search, archive and restore.
- A private photo and documents, through the shared file plumbing.
- An optional Teacher-role login.
- **Teaching assignments:** which subject a teacher teaches in which section.
- **Class teacher** per section.
- Real `teacher_stats` and teacher `assigned_subjects` dashboard widgets.

Excludes: teacher attendance, routine and marks entry (Modules 14/15/17 consume the assignment data). Public teacher profiles are Module 28. Payroll is V2. **Scoping the student list to a teacher's sections stays in Module 13**, because it needs enrollments to link students to sections.

## Design decisions

- **Assignments are per section, not per class.** This resolves the question carried over from Module 09. `teaching_assignments` has (`teacher_id`, `section_id`, `class_subject_id`), unique on (`section_id`, `class_subject_id`): one teacher per subject per section, so 6A Maths and 6B Maths can have different teachers.
  - The row points at the class's subject (`class_subjects`), so FK RESTRICT stops a subject being unassigned from a class while someone teaches it, or a section being deleted while it's taught.
  - The API takes `section_id` + `subject_id` and resolves the class-subject row server-side. This enforces the Module 08 rule that the section must belong to the class: a subject not taught in the section's class is refused with a clear message.
  - Assignments describe the **current** arrangement (no academic year); history lives with the records that use them (routines, marks).
- **Class teacher:** `sections.class_teacher_id` (nullable FK, RESTRICT), one per section, set and cleared through the teacher's endpoints and shown on the Sections screen. It's guarded against mass assignment.
- **Login:**
  - Like guardians, the login is linked through `users.personable` (alias `teacher`). It's created through the now-shared `App\Actions\Person\CreatePersonAccountAction` with the role fixed to `Teacher`.
  - Creating a login needs `teacher.edit` (also inline on `POST /teachers`), and the login's display name follows the teacher's.
- **Archive = a teacher who left (soft delete):**
  - Refused while they still teach or are a class teacher, so no section loses its teacher without a handover.
  - Archiving switches their login off in the same transaction. Restore brings the record back, but the login stays off until reactivated under Users.
- **Employee ID:** generated `T-0001` (`Sequence`) when left blank, skipping hand-typed values; unique across archived teachers.
- **Salary:** `DECIMAL(15,2)`, validated to 2 decimals, and returned as a decimal string, never a float. Anyone with `teacher.view` sees it (Admin, Principal, HR). It must never reach the public teacher profiles (Module 28).
- **Shared plumbing generalized in this module** (Module 11 code moved onto it, with its tests still passing):
  - **Documents:** one polymorphic `documents` table. A data migration moved `student_documents` into it, and both directions were tested on MariaDB.
  - **Owner trait:** `HasDocuments` (`documents()`, `fileFolder()`).
  - **Shared file actions:** `App\Actions\Files\{StoreDocument,DeleteDocument,StorePhoto,RemovePhoto}Action` and `StoreDocumentRequest` / `UploadPhotoRequest`.
  - **File routes:** a generic `/api/files/documents/{id}` link, and `PrivateFileController` serves student and teacher photos.
  - **Logins:** `CreatePersonAccountAction` + `AccountRules` / `CreateAccountRequest` now serve guardians and teachers.
  - **Morph map:** it now also registers `student` and `teacher`, so `PersonLink` validates those links from the Users screen.
  - **Frontend:** `FormField`, `DetailsList`, `PhotoEditor` and `DocumentsPanel` in `components/`.

## Database

- `teachers` — `employee_id` (unique), `name` (indexed), `designation`, `department` (indexed), `qualification`, `joining_date`, `phone`, `email` (contact), `address`, `salary` DECIMAL(15,2), `photo_path` (private), `deleted_at`, timestamps.
- `teaching_assignments` — `teacher_id`, `section_id`, `class_subject_id` (all FK RESTRICT), unique (`section_id`, `class_subject_id`).
- `sections.class_teacher_id` — nullable FK → `teachers` (RESTRICT).
- `documents` — polymorphic (`documentable_type`/`_id`), replaces `student_documents`.

## Permissions

`teacher.view|create|edit|delete` (seeded in Module 02). Admin and HR Manager manage; Principal views. Teachers get 403 on the staff register and see their own assignments on their dashboard. The assignment pickers in the UI also need `class.view`, `section.view` and `subject.view`. HR Manager doesn't hold those, so HR sees assignments read-only while Admin assigns them; grant them under Roles if HR should assign.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /teachers | teacher.view | paginated; `?search` (name, employee ID, phone, department), `?archived=1`, `?department`; rows carry `account`, `assignments_count`, `photo_url` |
| POST | /teachers | teacher.create (+ teacher.edit with `account`) | generates `employee_id` when blank; optional `account` {email, password} |
| GET | /teachers/{id} | teacher.view | profile incl. `assignments`, `class_teacher_of`, `account`, `documents`; works for archived |
| PUT | /teachers/{id} | teacher.edit | renames the login |
| DELETE | /teachers/{id} | teacher.delete | archive; 409 while teaching; disables the login |
| POST | /teachers/{id}/restore | teacher.delete | 409 if not archived |
| POST | /teachers/{id}/account | teacher.edit | Teacher-role login; 409 if one exists |
| POST / DELETE | /teachers/{id}/photo | teacher.edit | shared private photo |
| POST / DELETE | /teachers/{id}/documents[/{docId}] | teacher.edit | shared documents; delete scoped to the teacher |
| POST | /teachers/{id}/subjects | teacher.edit | `{section_id, subject_id}`; 422 if not taught in that class or already taught |
| DELETE | /teachers/{id}/subjects/{assignmentId} | teacher.edit | scoped to the teacher |
| POST | /teachers/{id}/classes | teacher.edit | `{section_id}`: class teacher; 422 if the section has one |
| DELETE | /teachers/{id}/classes/{sectionId} | teacher.edit | 404 unless they're its class teacher |

The plan's single "assign subject(s) to class(es)" endpoint became per-section endpoints, matching the per-section grain.

## Backend tasks

- [x] Migration + `Teacher` (soft deletes, `HasDocuments`), `TeachingAssignment`, `sections.class_teacher_id`
- [x] Form Requests (employee ID format/uniqueness, phone, email, 2-decimal salary, optional login; assignment ids)
- [x] Policy: permission middleware per route; ownership through the teacher's own dashboard widget. Student scoping → Module 13
- [x] Actions: create (transactional, optional login), update (renames login), archive (handover check + login off), `TeachingAssignments` (assign, class teacher)
- [x] Teacher role assigned via Spatie when a login is created (role fixed server-side)
- [x] API Resource + routes; `teacher_stats` and `assigned_subjects` widgets; local seed links `teacher@school.test` to a "Demo Teacher"

## Frontend tasks

- [x] `admin/teachers` list (search, archived toggle, photo, designation, department, subject count, login status) + nav entry
- [x] `admin/teachers/new` and `[id]/edit` form with per-field errors (salary sent as a decimal string)
- [x] `admin/teachers/[id]` profile: photo, details (formatted salary), teaching panel (class → section → subject pickers; assign / make class teacher; remove), portal-login panel, documents, archive/restore
- [x] Dashboard renderers (`teacher_stats` tiles, "My subjects" card); Sections screen shows the class teacher
- [ ] Browser click-through of the new screens (see Build notes)

## Acceptance criteria

- [x] Teacher CRUD works with unique `employee_id`
- [x] Subject/class assignment persists (`teaching_assignments` per section; `sections.class_teacher_id`)
- [x] Photo and documents are validated (MIME + max size) and rejected otherwise; a PHP file disguised as `.png` was rejected live
- [x] Assignment data is readable to scope teacher access: the teacher's dashboard reads its own assignments; Modules 14/15/17 will query `teaching_assignments`
- [x] List endpoint is paginated; tests written and passing (18 teacher tests; 176 backend tests total)
- [x] Security review: two-layer auth (permission per route + ownership for the teacher's own data); login creation needs `teacher.edit` and can't pick its role; salary decimal-only; uploads content-sniffed; documents scoped per owner (a teacher's document can't be deleted through a student URL, tested)

## Notes

`salary` is money → `decimal(15,2)` (spec §53), never float. Assignments are the source of truth for the teacher's authorization scope in later modules (spec §52). Enter-once/use-everywhere: this record feeds routine, portal and public profile (spec §68).

**Build notes (2026-10-05):**
- **Not yet clicked through in a browser.** The in-app browser tools were blocked for the rest of this session by auto mode's safety check (it reacts to earlier conversation content, not to these actions). So the new teacher screens and the refactored student profile weren't exercised in the browser. They compile, lint and type-check, and they call the endpoints verified below. **To finish:** open `/admin/teachers` and click through add → profile → assign → login → photo/document → archive (or rerun this check in a fresh session), then mark this module ✅.
- Verified live through the API on MariaDB, using the same calls the screens make:
  - **Migration:** the migrated student document still downloads (`attachment; filename=birth-certificate.pdf`).
  - **Create:** Farhana Akter was created with a login → `T-0001`, salary `"32000.50"`, Teacher role. The login signs in to the `teacher` audience.
  - **Assignments:** 6A and 6B Mathematics were assigned to Farhana. Science (not taught in Class 6) was refused with the message. The demo teacher was refused 6A Mathematics ("taught by Farhana Akter") and given 6A English.
  - **Class teacher:** Farhana became class teacher of 6A; a second class teacher was refused. The sections list shows "Farhana Akter" on A.
  - **Delete protection:** unassigning Mathematics from Class 6 while taught → 409.
  - **Photo:** a real PNG uploaded and served from the signed link (`image/png`). A PHP file named `.png` was rejected by both checks.
  - **Teacher's view:** Farhana's dashboard shows only her subjects and 6A; she gets 403 on `/teachers`.
  - **Archive:** a throwaway teacher was refused while teaching (409), archived after handover (204), and their login then reported "This account has been disabled." After restore, the record is back and the login is still off (then re-archived to keep the data tidy).
- Dev DB teachers:
  - **Farhana Akter** (`T-0001`): login `farhana.teacher@school.test`; 6A + 6B Mathematics; class teacher of 6A.
  - **Demo Teacher** (`T-DEMO`): login `teacher@school.test`; 6A English.
  - **Throwaway Teacher**: archived.

  All demo logins use the documented demo password.
