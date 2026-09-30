# Module 31 — Online Admission

- **Status:** ⬜ Not started
- **Phase:** Phase 5 — Website CMS
- **Completed on:** —
- **Owner agent:** `module-builder`

> Public online admission: a visitor submits an application at schooldomain.com/admission and receives an application number; admins review and approve/reject, and approval provisions a Student plus enrollment — with a rate-limited, spam-protected form and enumeration-resistant tracking.

## Scope

Includes (spec §10, §34): public application form → `POST /api/public/admissions`; generation of an opaque application number; admin review workflow (submitted → under review → approved/rejected); approval that provisions a Student (module 11) + enrollment (module 13); optional interview scheduling, merit info and admission fee flag; queued SMS/email confirmation; applicant tracking by application number only.

Excludes: payment-gateway processing for the admission fee (Payments module), full student CRUD (module 11). Spec §35 result lookup is a separate module.

## Dependencies

`11 — Students` (approval creates the student), `13 — Enrollments` (approval enrolls into class/year), `07 — Classes` (class applied for).

## Database (tables owned / referenced)

- `admission_applications` — id, application_no (unique, opaque/non-sequential), student_name, dob, gender, photo_path, father_name, mother_name, guardian_name, contact_phone, contact_email, previous_school, previous_class, previous_result, class_applying_id (FK classes), academic_year_id (FK), documents JSON, status enum(submitted, under_review, approved, rejected) default submitted, interview_at nullable, merit_position nullable, admission_fee_paid bool default false, review_notes text nullable, created_student_id (FK students, nullable), timestamps, soft deletes. Unique index application_no; index (status, class_applying_id).
- References: `students`, `student_enrollments`, `classes`, `academic_years`.

## Permissions

Introduces `admission.view`, `admission.manage` (spec §6 pattern). Public submit and public tracking require no authentication.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| POST | /public/admissions | — (public, throttled) | validated + spam-protected; returns application_no |
| GET | /public/admissions/{application_no}/status | — (public, throttled) | own application status only |
| GET | /website/admissions | admission.view | paginated; filter by status/class |
| GET | /website/admissions/{id} | admission.view | full application detail |
| PUT | /website/admissions/{id}/review | admission.manage | set under_review + notes |
| POST | /website/admissions/{id}/approve | admission.manage | provisions Student + enrollment (transaction) |
| POST | /website/admissions/{id}/reject | admission.manage | notifies applicant |
| PUT | /website/admissions/{id}/interview | admission.manage | schedule interview |

## Backend tasks

- [ ] Migration + model (opaque application_no generator, indexes, soft deletes)
- [ ] Public Form Request: full validation, honeypot/CAPTCHA-style spam check, MIME/size-validated photo + documents (spec §51)
- [ ] Rate limiting (`throttle`) on public submit + tracking routes to resist scraping/enumeration
- [ ] `ApproveAdmissionAction` inside a DB transaction (spec §55): create student, create enrollment, link created_student_id, dispatch confirmation
- [ ] Queued SMS/email confirmation + reject notifications (spec §45, §61)
- [ ] Resources: internal admin resource (full) vs public tracking resource (status only)
- [ ] Routes: `/website/*` JWT + `permission:` middleware; `/public/*` unauthenticated + throttled

## Frontend tasks

- [ ] `(public)/admission` — multi-step form (student/parent/previous education/uploads) + confirmation screen showing application_no
- [ ] `(public)/admission/track` — lookup by application_no returning status only
- [ ] `admin/admissions` — review queue, detail, approve/reject, interview scheduling (paginated)
- [ ] Typed `admissionService` (submit, track) + `adminAdmissionService`

## Acceptance criteria

- [ ] Public submit returns an application_no and persists the application
- [ ] Admin can review then approve or reject
- [ ] Approval provisions a Student + enrollment atomically (rollback on failure)
- [ ] Reject notifies the applicant (queued)
- [ ] Tracking returns only the matching application's status — no listing or data enumeration
- [ ] Public form is validated, rate-limited and spam-protected; uploads validated
- [ ] Invalid/unknown application_no returns a generic not-found
- [ ] Tests written and passing (submit, approve provisioning, reject notify, throttle, enumeration guard)
- [ ] Security review passed (opaque IDs, throttling, upload validation, transaction integrity)

## Notes

application_no must be opaque and non-sequential (e.g. random token) so applicants cannot guess others' numbers (spec §35 enumeration concern applied here). The tracking endpoint exposes only status, never the full application or PII lists. Approval is the single provisioning path into Students/Enrollments, keeping "enter data once" (spec §68); wrap it in a transaction (spec §55).
