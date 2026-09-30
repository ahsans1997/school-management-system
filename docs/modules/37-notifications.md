# Module 37 — Notifications

- **Status:** ⬜ Not started
- **Phase:** Phase 7 — Reporting, Communication & Audit
- **Completed on:** —
- **Owner agent:** `module-builder`

> Laravel Notifications over in-app (database), email, SMS, and optional WhatsApp channels (spec §36, §45) — driven by system events and by scheduled reminders, with expensive channels queued so requests never block (spec §61).

## Scope

Includes (spec §36, §45): notifications across four channels — database (in-app), email, SMS, and WhatsApp (optional) — triggered by new notice, fee reminder, exam reminder, attendance alert, result published, admission confirmation, and event reminder. Editable `notification_templates` per notification type/channel. Event-driven fan-out (spec §45): a fee payment triggers a receipt notification plus parent and admin notifications. Scheduler jobs (spec §61) for daily fee reminders, daily attendance summary, upcoming exam reminders, and monthly report notifications. In-app list + mark-read API.

Excludes: the actual report generation (Module 36), notice/event content management (Modules 25/28), and the payment transaction itself (Module 20) — this module only listens and delivers. WhatsApp is an optional integration (spec §36) and may be a V2 gateway.

## Dependencies

`03 — Users` (notifiable recipients + channel routing), `25 — Notices`, `20 — Payments` (fee-payment fan-out), `18 — Results` (result-published trigger), `31 — Admission` (admission confirmation).

## Database (tables owned / referenced)

- `notifications` — Laravel's default polymorphic table: id (uuid), type, notifiable_type, notifiable_id, data (json), read_at (nullable), timestamps. Index (notifiable_type, notifiable_id, read_at) for the in-app inbox.
- `notification_templates` — id, key (unique, e.g. `fee_reminder`), channel (enum: database/email/sms/whatsapp), subject (nullable), body (with `{{placeholders}}`), is_active, timestamps, soft deletes. Unique (key, channel).
- References: `users`, `notices`, `payments`, `results`, `admissions` (source events); channel config from `04 — School Settings` (email/SMS settings).

## Permissions

`notification.view` (read own in-app inbox + mark read — all authenticated users hold it for their own records) and `notification.manage` (template CRUD, admin/principal). Two-layer authorization (spec §52): the inbox is policy-scoped so a user reads and marks read only their own notifications; template management requires `notification.manage`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /notifications | notification.view | paginated; caller's in-app notifications, unread filter |
| GET | /notifications/unread-count | notification.view | badge count |
| POST | /notifications/{id}/read | notification.view | mark one read (owner-scoped) |
| POST | /notifications/read-all | notification.view | mark all read |
| GET | /notification-templates | notification.manage | paginated |
| POST | /notification-templates | notification.manage | create |
| PUT | /notification-templates/{id} | notification.manage | edit subject/body/active |

## Backend tasks

- [ ] Migration for `notification_templates` (+ Laravel `notifications` table); models
- [ ] Notification classes per trigger (NewNotice, FeeReminder, ExamReminder, AttendanceAlert, ResultPublished, AdmissionConfirmation, EventReminder) with `via()` per recipient channel routing
- [ ] Custom SMS + WhatsApp channels; email/SMS/WhatsApp notifications `implements ShouldQueue` (spec §61)
- [ ] Event listeners for fan-out (spec §45): PaymentRecorded → receipt + parent + admin; ResultPublished → students/parents
- [ ] Scheduler entries: daily fee reminder, daily attendance summary, upcoming exam reminder, monthly reports
- [ ] Form Requests + Policy (template `authorize()`; inbox ownership scope)
- [ ] API Resources for inbox + template; routes under `/api` with JWT + `permission:` middleware

## Frontend tasks

- [ ] In-app notification bell (unread count) + dropdown/list with mark-read, under all role areas
- [ ] Admin template management under `admin/settings/notifications/` (list + edit, placeholder help)
- [ ] Typed `notificationService` in `services/` (list, unread-count, mark-read, templates CRUD)
- [ ] Role-appropriate visibility — inbox shows only the caller's notifications; templates admin-only

## Acceptance criteria

- [ ] An in-app (database) notification is delivered and can be marked read (owner-scoped)
- [ ] Email/SMS notifications are queued, not sent inline — the triggering request is not blocked (spec §61)
- [ ] Templates are editable and applied to outgoing notifications (placeholder substitution)
- [ ] Scheduled reminders fire (daily fee reminder, attendance summary, exam reminder, monthly reports)
- [ ] Result-published notifies the relevant users (students/parents of that class/exam)
- [ ] A fee payment fans out to receipt + parent + admin notifications (spec §45)
- [ ] Tests written and passing (in-app delivery, queued channels, template render, scheduled trigger, fan-out)
- [ ] Security review passed (two-layer auth, inbox ownership scope, no channel secrets exposed)

## Notes

Recipient channel routing (email vs SMS vs in-app) is resolved per user/setting, not from the client. Email/SMS/WhatsApp/bulk always queue (spec §61); only database notifications may deliver synchronously. WhatsApp ships behind a feature flag and may land as a V2 gateway (spec §36, §66). Fan-out is event-driven so trigger modules stay decoupled from delivery (spec §49).
