# Module 16 — Exams

- **Status:** ✅ Complete
- **Phase:** Phase 3 — Academic Operations
- **Completed on:** 2026-10-05
- **Owner agent:** `module-builder`

> Exam definitions (monthly, half-yearly, annual, model, test, custom) with per-class papers (full marks, pass marks, optional CQ/MCQ/practical split, date and time) and configurable grade scales that marks and results depend on.

## Scope

Included (spec §16):
- **Exams:** tied to an academic year and a type, with start and end dates (a blank end date means a one-day exam) and the grade scale its results use.
- **Papers** (exam subjects), set per class:
  - full and pass marks, and an optional CQ / MCQ / practical split;
  - optional date and start/end times;
  - sat by every section of the class unless limited to specific sections.
- **Schedule:** the whole exam, one class, or one section; printable. Each portal also sees its own (`GET /exams/me`).
- **Grade scales:** grade bands with grade points.
- **Dashboard:** "Upcoming exams" for admins and for the teacher, student and parent portals.

Excludes: marks entry (Module 17), result calculation and publishing (Module 18), admit cards and seat plans (deferred).

## Design decisions

- **Papers reference `class_subjects`**, not a class and a subject separately.
  - A composite FK `(class_subject_id, class_id)` → `class_subjects(id, class_id)` means the database refuses a subject the class doesn't take. Removing a subject from a class that an exam uses → 409.
  - Marks entry (Module 17) can match a paper to a teacher's `teaching_assignments` directly (same `class_subject_id` + section).
- **Sections:** `section_id` NULL = every section; set = only that section (e.g. a Science-group section's Physics). A subject is either class-wide or per-section, never both, so there's no override ambiguity. A section sits its class's class-wide papers plus its own (`ExamSubject::forSection`).
  - Unique `(exam_id, class_subject_id, section_key)`, where `section_key` is a stored generated column `coalesce(section_id, 0)`. NULLs never collide in a unique index, so without it two class-wide rows for the same subject would both be accepted.
- **Saving a class's papers is a whole-set replace, applied as a diff:** rows are matched by subject + section and updated in place (so marks can point at them), new ones are created, missing ones removed. An empty list takes the class out of the exam. Errors are keyed `subjects.N.field` so the editor marks the row. Saves for an exam are serialized (row lock).
- **Paper checks:**
  - pass ≤ full;
  - the split (when given) adds up to the full marks;
  - the date falls inside the exam;
  - times are given both-or-neither, and end after they start.
  - Two timed papers on the same day can't overlap for anyone who sits both (back-to-back is fine; different sections at the same time are fine).
  - Marks are compared as integer hundredths, so sums are exact.
- **Exam header:**
  - Dates must fall within its academic year.
  - The year is fixed at creation.
  - Moving the dates is refused while scheduled papers would fall outside them.
  - Names are unique per year, including archived exams, so restoring can't create a duplicate.
- **Archive, not delete:** `DELETE /exams/{id}` soft-deletes. Papers (and later marks and results) are kept, archived exams are read-only (only show and restore bind them), and `POST /exams/{id}/restore` brings one back.
- **Grade scales are cut-offs, not ranges.** Each band stores only `min_percent` and runs up to the next band, so bands can't overlap or leave gaps (stored as "70–79" and "80–100", a 79.5 % mark would have no grade).
  - The service requires one band at 0 %, unique names and cut-offs, and grade points that never rise as the cut-off falls.
  - Grading is by percentage of the paper's full marks, so 50-mark papers grade the same way (`GradeScale::gradeFor()`).
  - Editing a scale keeps the bands it is sent (by id) and updates them in place, so later result rows can reference grades.
- **Versioning:** each exam picks its scale (`exams.grade_scale_id`, RESTRICT), so a new year can use a new scale while old exams keep theirs. A scale in use can't be deleted (409).
- **Access:**
  - Exam setup needs `exam.create` / `exam.edit` / `exam.delete`.
  - Grade-scale writes need the new **`exam.grading`** permission (Admin only by default), since a change regrades every result that uses the scale.
  - `exam.view` (Admin, Principal, Teacher) reads exams, papers, schedules and scales. Schedules aren't sensitive, so teachers aren't section-scoped here.
  - `GET /exams/me` needs no exam permission: it's ownership-based (teacher → their sections, student → own section, parent → each child), current year, upcoming and ongoing exams only. Parents and students get 403 on everything else.

## Database

- `exams`:
  - Columns: `academic_year_id` (RESTRICT), `grade_scale_id` (RESTRICT), `name`, `type` (`App\Enums\ExamType`), `start_date`, `end_date` (nullable), timestamps, soft deletes.
  - Indexes: unique (`academic_year_id`, `name`); index (`academic_year_id`, `type`).
- `exam_subjects`:
  - Columns: `exam_id` (cascade), `class_id`, `class_subject_id` (composite FK with class), `section_id` (nullable, composite FK with class), `section_key` (generated), `full_marks` / `pass_marks` / `cq_marks` / `mcq_marks` / `practical_marks` (DECIMAL(6,2), split nullable), `exam_date`, `start_time`, `end_time` (all nullable), timestamps.
  - Indexes: unique (`exam_id`, `class_subject_id`, `section_key`); index (`exam_id`, `class_id`); index (`exam_date`).
- `grade_scales` — `name` (unique), timestamps.
- `grades`:
  - Columns: `grade_scale_id` (cascade), `name`, `grade_point` DECIMAL(4,2), `min_percent` DECIMAL(5,2), timestamps.
  - Indexes: unique (`grade_scale_id`, `name`) and (`grade_scale_id`, `min_percent`).
- `class_subjects` gains unique (`id`, `class_id`) as the composite FK target.
- These replace the doc's original `grades` (name + point) / `grade_scales` (min/max per grade) split and `subject_id` column.

## Permissions

`exam.view|create|edit|delete` (seeded in Module 02): Admin all; Principal and Teacher `.view`. **New:** `exam.grading` (Admin only; the catalogue is now 81 permissions). `exam.marks_entry` is for Module 17. Students and parents use `/exams/me`.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /exams | exam.view | paginated; `academic_year_id`, `type`, `archived` filters; papers/classes counts |
| POST | /exams | exam.create | header: name, type, year, grade scale, dates |
| GET | /exams/{id} | exam.view | + `classes` taking it (with paper counts); archived too |
| PUT | /exams/{id} | exam.edit | year fixed; can't strand scheduled papers |
| DELETE | /exams/{id} | exam.delete | archive (soft delete) |
| POST | /exams/{id}/restore | exam.delete | |
| GET | /exams/{id}/schedule | exam.view | `?class_id=` or `?section_id=` |
| GET | /exams/{id}/classes/{classId}/subjects | exam.view | the class's papers + its subjects and sections (editor) |
| PUT | /exams/{id}/classes/{classId}/subjects | exam.edit | `{subjects: [...]}`: replace the class's papers |
| GET | /exams/me | authenticated | teacher / student / parent: upcoming exams for their sections |
| GET | /grade-scales | exam.view | every scale with its bands |
| POST / PUT / DELETE | /grade-scales[/{id}] | exam.grading | bands as cut-offs; send a band's id to keep it |
| GET | /grade-scales/{id} | exam.view | |

## Backend tasks

- [x] Migration + models (`Exam` soft deletes, `ExamSubject`, `GradeScale`, `Grade`, `ExamType`), composite FKs, generated `section_key`
- [x] Form Requests (`SaveExamRequest`, `SaveExamSubjectsRequest`, `SaveGradeScaleRequest`)
- [x] `GradeScaleService` (coverage, no overlaps, monotonic points, in-place band edits); `SaveExam`; `SaveExamSubjects` (diff save + row checks + overlaps); `ExamScheduleService` (schedules, `/me`)
- [x] Edit/delete scope by permission; `exam.grading` for scales (the doc's `ExamPolicy`)
- [x] `ExamResource` / `ExamSubjectResource` / `GradeScaleResource`
- [x] Routes; real `upcoming_exams` widget replaces its placeholder (admin + portals)

## Frontend tasks

- [x] `admin/exams` list (year/type/archived filters) + create form; nav entry
- [x] `admin/exams/[examId]`: details + edit, archive/restore, classes taking it, add a class, schedule with class/section filter + print
- [x] `admin/exams/[examId]/classes/[classId]`: papers grid (subject, sections, full/pass, CQ/MCQ/practical, date, times; per-row errors; a new class starts with all its subjects)
- [x] `admin/grade-scales` editor (starts from the standard GPA 5.00 bands; shows each band's range)
- [x] Portal pages `teacher/exams`, `student/exams`, `parent/exams` (via `/exams/me`) + nav entries; dashboard "Upcoming exams" card
- [x] Typed `examService` in `services/`
- [x] Browser click-through (2026-10-05, see Build notes)

## Acceptance criteria

- [x] An exam is configured per class with each subject's full and pass marks
- [x] A grade scale is defined with non-overlapping bands and grade points
- [x] The exam schedule is listable per class/section
- [x] pass_marks ≤ full_marks enforced; duplicate exam-subject blocked by unique key
- [x] Tests written and passing (15 exam tests; 234 backend tests total)
- [x] Security review: setup gated by exam.* permissions; grade-scale writes by `exam.grading` (Admin only); `/exams/me` ownership-only; parents/students blocked from the rest

## Notes for Modules 17–18

- **Marks (17):**
  - A paper with marks can't be removed: the 409 is already handled once `marks` references `exam_subjects` with RESTRICT.
  - 17 should also stop the full marks or the split being lowered below marks already entered.
  - Component caps come from `cq_marks` / `mcq_marks` / `practical_marks`. A paper without a split takes a single mark.
- **Results (18):**
  - Grade on percentage of full marks via `GradeScale::gradeFor()`.
  - A subject below its `pass_marks` fails whatever the band says.
  - Lock scale edits (or snapshot grade name + point on results) once results using the scale are published.
- Per-component pass marks (pass CQ and MCQ separately, as in SSC) aren't modelled yet.

**Build notes (2026-10-05):**
- **Clicked through in the browser (2026-10-05):**
  - Grade scales: GPA 5.00 shows its ranges ("70 to under 80 %"); Delete is hidden while an exam uses it. A new scale starts from the standard bands; giving B a higher point than A- was refused on B's row; the scale then saved and deleted.
  - New exam: a December 2025 start was refused under Start date; "Click Test Exam" (25–27 Nov) was created with the current year and the only scale preselected.
  - Class 6 papers: opened prefilled with the class's three subjects at 100 / 33. An overlap (Bangla 10:00–12:00, English 11:00–13:00 on 25 Nov) was refused under English's row; after moving English, 3 papers saved, sorted by date, with Maths split CQ 70 + MCQ 30.
  - Exam page: the schedule filtered to 6A (class column hidden). Shortening the end date was refused, and renaming saved. Archive hid the schedule and paper links; Restore brought them back.
  - **Changed:** the "papers outside these dates" error now sits under whichever date strands them (it was always under Start date).
  - Portals: Farhana's Exams shows 6A and 6B, karim.parent's shows Nusrat's 6A papers, and an unlinked student login gets "Your login isn't linked to a student yet." Every dashboard shows Upcoming exams.
  - The test exam was removed afterwards.
- Verified live through the API on MariaDB:
  - **Grade scale:** created "GPA 5.00". A scale without a 0 % band was refused. A teacher creating one got 403.
  - **Exam header:** created "Half Yearly 2026" (10–20 Nov). An exam starting in 2025 was refused.
  - **Class 6 papers:**
    - Bangla 10 Nov, Maths (CQ 70 + MCQ 30) 12 Nov, English 15 Nov, all 10:00–13:00.
    - Re-saving kept the paper ids.
    - Overlap, a split not adding up, pass above full, and a date outside the exam were each refused on the right row.
  - **Class 7 papers:** Bangla 11 Nov, Maths 13 Nov.
  - **Schedule:** whole-exam order was correct, and 6B's schedule was correct when read as a teacher. Shortening the exam to 12 Nov was refused ("2 papers are scheduled outside these dates").
  - **Portals:** Farhana's `/exams/me` shows 6A and 6B. karim.parent sees Nusrat's 6A papers and gets 403 on `/exams`. The `upcoming_exams` widget is right for admin (5 papers, 2 classes), Farhana and karim.
  - **Guards:** removing Bangla from Class 6 → 409; deleting the scale in use → 409. A one-day exam refused a paper on another day. Archive → hidden, schedule 404; restore worked (scratch exam removed afterwards).
- Dev DB now has the "GPA 5.00" scale and "Half Yearly 2026" (Class 6: 3 papers, Class 7: 2 papers).
