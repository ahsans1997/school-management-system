# Module 15 — Class Routine / Timetable

- **Status:** ✅ Complete
- **Phase:** Phase 3 — Academic Operations
- **Completed on:** 2026-10-05
- **Owner agent:** `module-builder`

> Weekly timetables per section, built from period slots, rendering as class, teacher, room and "my routine" views from the same data, with teacher double-booking, room clash and overlap detection (spec §15).

## Scope

Included:
- **Routine:** one per section per academic year: a full week of periods (day, period no., start/end, subject + teacher **or** a labelled non-teaching period such as Break, and room).
- **Conflicts block saving:**
  - a teacher in two places at once (across all sections of the year);
  - a room used by two sections at once;
  - periods of the section overlapping or repeating.
- **Views:** section (class) routine, teacher routine, room routine, and **`/routines/me`**: a teacher's own week, a student's own section, a parent's children.
- **Dashboard:** "Today's classes" for teachers and "Today's periods" for students.

Excludes: automatic timetable generation, break templates beyond labelled periods, substitute teachers, and effective-dated versions (see Notes).

## Design decisions

- **Teaching periods are built from teaching assignments** (Module 12): a slot points at `teaching_assignments` (section × subject × teacher), not at a subject and a teacher separately. So the routine can never disagree with who teaches what (enter once), and the builder offers exactly the section's assignments ("Mathematics — Farhana").
  - An assignment in use by a routine **can't be removed** (FK RESTRICT → 409 "This subject is in the class routine. Remove those periods first.").
  - Handing a subject to another teacher therefore means editing those periods. A "reassign the teacher on an assignment" action (re-checking clashes) would remove that step later.
- **Clash detection** (`RoutineService::clashes`) compares **time-range overlaps** within a day (`a.start < b.end && b.start < a.end`), so back-to-back periods are fine.
  - Rooms compare case- and space-insensitively, whatever the DB collation.
  - The 422 carries `clashes: [{index, type: teacher|room|section, message}]` and `errors["slots.N"]`, so the builder marks the offending row.
  - Saves for a year are serialized (lock on the year's routines), so two sections can't grab the same teacher or room concurrently.
- **Whole-week saves:** `POST /routines` creates or replaces a section's week (replacing also needs `routine.edit`); `PUT /routines/{id}` replaces it too.
- **Week order** starts Saturday (`App\Enums\Weekday`), matching a Bangladeshi school week; only days that have periods are shown.
- **Access:**
  - Editing needs `routine.create` / `routine.edit` (Admin).
  - Viewing needs `routine.view` (Principal, Teacher). Teachers only see their own sections' routines (`TeacherScope`, 403 otherwise) and only their own teacher routine.
  - `/routines/me` needs no routine permission: it's ownership-based (teacher record, student record, guardian's children). Parents and students get 403 on everything else.

## Database

- `routines` — `academic_year_id`, `class_id`, `section_id` (composite FK with class), timestamps; unique (`section_id`, `academic_year_id`).
- `routine_slots` — `routine_id` (cascade), `day`, `period_no`, `start_time`, `end_time`, `teaching_assignment_id` (nullable FK, RESTRICT), `label`, `room`, timestamps.
  - Unique (`routine_id`, `day`, `period_no`).
  - Indexes (`teaching_assignment_id`, `day`) and (`day`, `room`) for clash and teacher/room views.
  - The doc's direct `subject_id` / `teacher_id` columns are replaced by the assignment reference.

## Permissions

`routine.view|create|edit` (seeded in Module 02): Admin all; Principal and Teacher `.view`. Students and parents use `/routines/me`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /routines | routine.view | routines of a year (default current) with slot counts; teachers: own sections |
| GET | /sections/{id}/routine | routine.view | the section's week + the builder's subject/teacher options; teacher-scoped |
| POST | /routines | routine.create (+ .edit to replace) | `{section_id, academic_year_id, slots[]}`; 201 created / 200 replaced; 422 with `clashes` |
| PUT | /routines/{id} | routine.edit | `{slots[]}`: replace the week |
| GET | /routines/teacher/{id} | routine.view | a teacher's week across sections; teachers: only their own |
| GET | /routines/room?room= | routine.view | who uses a room when |
| GET | /routines/me | authenticated | teacher → own week; student → own section; parent → each child |

## Backend tasks

- [x] Migrations + models (`Routine`, `RoutineSlot`, `Weekday`), composite FK, clash/view indexes
- [x] `SaveRoutineRequest` (day, period, HH:MM times, subject-or-label, room) + action checks (end after start, assignment belongs to the section)
- [x] `RoutineService` (views + clash detection) and `SaveRoutine` (transactional replace, serialized per year, structured 422)
- [x] Edit scope by permission, view scope by `TeacherScope`, ownership for `/me` (the doc's `RoutinePolicy`)
- [x] Routes; `todays_classes` + `my_routine` widgets replace their placeholders; scheduled assignments guarded

## Frontend tasks

- [x] `admin/routines` builder (class → section; period rows with day/number/times/subject—teacher or label/room; "Add period" continues from the last; per-row clash messages; week preview) + room timetable lookup; nav entry
- [x] Shared read-only `RoutineGrid` (section / teacher / room perspectives)
- [x] Portal pages: `teacher/routine`, `student/routine`, `parent/routine` (via `/routines/me`), plus portal navs for students and parents; teacher profile "Weekly routine" panel
- [x] Dashboard cards for today's periods (teacher, student)
- [x] Browser click-through (2026-10-05, see Build notes)

## Acceptance criteria

- [x] No teacher is double-booked in the same time slot (conflict flagged, save blocked)
- [x] A room conflict across sections is flagged
- [x] A student sees exactly their own section routine
- [x] Teacher and room views derive from the same slot data (no duplicate entry)
- [x] Tests written and passing (11 routine tests; 219 backend tests total)
- [x] Security review: edit gated by permission; teachers scoped to their sections and own routine; `/me` ownership-only; students/parents blocked from other routines

## Notes

Room stays free text in V1; a `rooms` table can replace it later without changing the view APIs. **Effective-dated routines** (mid-term versions) were left out: a save replaces the week. If history is needed, add versioned routines with `effective_from`.

**Build notes (2026-10-05):**
- **Clicked through in the browser (2026-10-05):**
  - The builder loaded 6B's week. A new Sun 08:00–08:40 Maths period was refused, with the message on top and under that row ("Farhana Akter already teaches Class 6 A on Sunday 08:00–08:40 (Mathematics).").
  - Removing that row and saving gave "Routine saved for Class 6 B."; the room 101 lookup showed the three 6A periods.
  - Farhana's My routine shows Sunday Maths in 6A (101) and 6B (102). karim.parent's Class routines shows Nusrat's 6A week (Sunday + Monday, Tiffin included).
  - A teacher opening `/admin/...` is sent back to `/teacher`.
- Verified live through the API on MariaDB:
  - **Builder options:** 6A offers English — Demo Teacher and Mathematics — Farhana Akter.
  - **6A week saved:** Sun P1 Maths 08:00–08:40 (101), P2 English 08:40–09:20 (101), P3 Tiffin, Mon P1 English (101).
  - **Clashes:** 6B Maths at 08:20 was refused ("Farhana Akter already teaches Class 6 A on Sunday 08:00–08:40 (Mathematics)."). 6B in room 101 at 08:40 was refused ("Room 101 is used by Class 6 A on Sunday 08:40–09:20."). Room 102 was accepted.
  - **Views:** Farhana's `/routines/me` shows 6A + 6B Maths. karim.parent's shows Nusrat's 6A week. Room 101 lists the three 6A periods.
  - **Scope:** Farhana → demo teacher's routine 403; parent → 6B routine 403. Removing the scheduled 6A Maths assignment → 409.
- Dev DB routines (2026): 6A (4 periods) and 6B (Sun P2 Maths, room 102).
