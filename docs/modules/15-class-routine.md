# Module 15 — Class Routine / Timetable

- **Status:** ⬜ Not started
- **Phase:** Phase 3 — Academic Operations
- **Completed on:** —
- **Owner agent:** `module-builder`

> Weekly timetables built from period slots (day, time, subject, teacher, room) that render as class, teacher, room, and student views, with automatic teacher-double-booking and room-clash detection.

## Scope

Includes (spec §15): create a routine per class-section, define period slots (day, start/end time, subject, teacher, room), and generate four views from the same data — class routine, teacher routine, room routine, and the student's own section routine. Detect and flag conflicts: a teacher booked in two slots at the same time, and a room used by two sections at the same time.

Excludes: automatic timetable generation/optimization, break/prayer-time templates beyond simple non-teaching slots, and substitute-teacher scheduling (deferred).

## Dependencies

`09 — Subjects` (slot subject), `12 — Teachers` (slot teacher + clash source), `08 — Sections` (routine owner). Requires `06 — Classes` transitively.

## Database (tables owned / referenced)

- `routines` — id, class_id (FK), section_id (FK), academic_year_id (FK), effective_from nullable, timestamps. Unique (section_id, academic_year_id). Index (section_id).
- `routine_slots` — id, routine_id (FK, cascade), day enum(Sun..Sat), period_no, start_time, end_time, subject_id (FK), teacher_id (FK), room varchar, timestamps. Indexes: (routine_id, day), (teacher_id, day, start_time), (room, day, start_time) to power clash queries and teacher/room views.
- References: `subjects`, `teachers`, `sections`, `classes`.

## Permissions

Introduces `routine.view`, `routine.create`, `routine.edit`. Two-layer authorization (spec §52): editing is admin/coordinator scoped; a teacher with `routine.view` sees their own teacher routine and their assigned sections, not the global editor.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /routines | routine.view | paginated; filter class/section |
| POST | /routines | routine.create | create routine + slots; clash-checked |
| PUT | /routines/{id} | routine.edit | update slots; re-runs clash check |
| GET | /routines/teacher/{teacherId} | routine.view | teacher view |
| GET | /routines/room/{room} | routine.view | room view |
| GET | /routines/student/me | routine.view | student's section routine |

## Backend tasks

- [ ] Migrations + models (FKs, composite indexes for clash + views)
- [ ] Form Requests (`StoreRoutineRequest`) validating time ranges + `authorize()`
- [ ] `DetectRoutineClashService` — teacher and room overlap detection (no logic in controllers)
- [ ] `RoutinePolicy` for edit scope
- [ ] `RoutineResource` / `RoutineSlotResource`
- [ ] Routes under `/api` with JWT + `permission:` middleware
- [ ] Return structured clash errors (which slot, which teacher/room) on 422

## Frontend tasks

- [ ] Admin: routine builder grid (day × period) with subject/teacher/room pickers
- [ ] Teacher/Student/Parent: read-only routine views
- [ ] Typed `routineService` in `services/` (class, teacher, room, student views)
- [ ] Inline clash warnings surfaced from the API

## Acceptance criteria

- [ ] No teacher is double-booked in the same time slot (conflict flagged, save blocked)
- [ ] A room conflict across sections is flagged
- [ ] A student sees exactly their own section routine
- [ ] Teacher and room views derive from the same slot data (no duplicate entry)
- [ ] Tests written and passing (teacher clash, room clash, student view scope)
- [ ] Security review passed (edit scope enforced by policy)

## Notes

Clash detection compares time-range overlaps within a day, not just equal start times. Room is a free-text/lookup string in V1; a dedicated `rooms` table can replace it later without changing the view APIs. Effective-dated routines allow mid-term changes.
