# Module 18 — Results

- **Status:** ✅ Complete
- **Phase:** Phase 3 — Academic Operations
- **Completed on:** 2026-10-20
- **Owner agent:** `module-builder`

> Computes results from entered marks (total, grade, GPA, position/merit), produces mark sheets, report cards, tabulation sheets and statistics, and moves them through a staged draft → review → approved → published → public workflow with auditing.

## Scope

Includes (spec §17, §56): calculate per-student results from `marks` (subject grades, total, GPA, overall grade, class position/merit), generate report card, mark sheet, tabulation sheet and result statistics, and run the staged publishing workflow — draft → review → approved → published — before any public exposure. Publishing is audited.

Excludes: the public-facing lookup endpoint and student-data minimization for it (Module 32 — Public Result); marks entry (Module 17). Only published results are handed to the public module.

## Dependencies

`17 — Marks Entry` (source marks), `16 — Exams` (grade scale, full/pass marks, exam-subject map). Reads enrollment for class position grouping.

## Database (tables owned / referenced)

**`results`:**
- Columns: id, exam_id, student_id, student_enrollment_id, class_id, section_id, total_marks / full_marks decimal(8,2), percentage decimal(5,2), gpa decimal(4,2), grade (string), passed, failed_subjects, position (in section, nullable), class_position (nullable), status (`draft` | `review` | `approved` | `published`), calculated_at / calculated_by, published_at / published_by, timestamps.
- Foreign keys are RESTRICT; section and class share a composite FK.
- Unique (exam_id, student_id). Indexes (exam_id, class_id, section_id) and (status).

**`result_details`** (one per subject):
- Columns: result_id (cascade), exam_subject_id (RESTRICT), subject and subject_code snapshot, full/pass marks snapshot, cq / mcq / practical, obtained (NULL = absent), absent, percentage, grade, grade_point, passed.
- Unique (result_id, exam_subject_id).

**Snapshots instead of `grade_id` FKs.** Grade names, points and subject names are copied onto the result. Editing a grade scale or renaming a subject later then can't change a published report card. This also answers Module 16's note on locking scale edits.

**`audit_logs`** is created now, with the schema Module 38 plans: user, action, module, auditable morph, old/new JSON, IP, user agent, created_at. It is append-only: the model throws on update or delete. It's written through `App\Support\Audit::record()`.

**References:** `marks` (submitted only), `exam_subjects`, `grade_scales`/`grades`, `student_enrollments`.

## Permissions

| Permission | Allows | Default roles |
|---|---|---|
| `result.view` | Reading lists, tabulation, statistics and report cards | Admin, Principal, Teacher |
| `result.process` (new; 82 permissions total) | Calculate; send for review | Admin |
| `result.publish` | Approve, publish, return to draft, unpublish | Admin, Principal |

Two-layer (spec §52):
- Teachers reading results see only their own sections (`TeacherScope::sectionIds`), and only for the current year's exams (assignments carry no year). Other sections' report cards return 404, and the overview's figures count their sections only.
- Calculating and every workflow step fail closed for teachers (403) even if a teacher role is given `result.process` / `result.publish`.
- `/results/me` is ownership-only: a student's own results, or a parent's children's, and only **published** ones.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET | /exams/{id}/results/classes | result.view | per class: mark sheets submitted/total (+ a few pending), results status, students/passed |
| POST | /exams/{id}/results/calculate | result.process | `{class_id}`: draft results from submitted marks; 422 lists unsubmitted sheets; 409 once past draft |
| POST | /exams/{id}/results/review | result.process | draft → review |
| POST | /exams/{id}/results/approve | result.publish | review → approved |
| POST | /exams/{id}/results/publish | result.publish | approved → published (sets published_at/by) |
| POST | /exams/{id}/results/return | result.publish | review / approved → draft |
| POST | /exams/{id}/results/unpublish | result.publish | published → approved (to correct and re-publish) |
| GET | /exams/{id}/results | result.view | merit list, paginated; `?class_id` (required), `?section_id` |
| GET | /exams/{id}/results/tabulation | result.view | students × subjects, merit order |
| GET | /exams/{id}/results/statistics | result.view | pass rate, average GPA, grade spread, by section, by subject |
| GET | /results/{id}/report-card | result.view | one student's mark sheet |
| GET | /results/me | (ownership) | published results of the caller / their children |

Every workflow step is audited in the same transaction: `results.calculated`, `results.sent_for_review`, `results.approved`, `results.published`, `results.returned`, `results.unpublished`.

## Backend tasks

- [x] Migrations + models (`Result`, `ResultDetail`, `ResultStatus`, `AuditLog`); FKs, unique, status/grouping indexes
- [x] `CalculateResults`: server-side, in hundredths (rules under Notes)
- [x] `ResultWorkflow`: one transition table, steps checked against the current status (409), permissions on routes
- [x] Freezing:
  - Past draft, marks reopen, marks edits, paper edits, the exam's grade-scale switch and recalculation are all refused (409/422).
  - Reopening marks or changing papers clears stale **draft** results.
  - Reopen and submit take the exam lock first, like calculation and the workflow, so they can't interleave.
  - Review / approve / publish re-check that every sheet is still fully submitted and that the results were calculated after the grade scale last changed (`stale` is shown in the overview).
- [x] `ResultService`: sheet progress, overview, merit order, tabulation, statistics, report card, `mine()`
- [x] Routes under `/api` with JWT + `permission:` middleware; drafts never serialized to students, parents or anything public
- [x] Real `my_results` (student) and `child_results` (parent) widgets replace their placeholders: published only

## Frontend tasks

- [x] `admin/results` and `teacher/results`:
  - Exam picker.
  - Per class: marks submitted (with what's still waiting) and status badge.
  - Buttons by permission and status: Calculate / Recalculate, Send for review, Approve, Publish (confirm), Return to draft, Unpublish (confirm).
- [x] `…/results/class`:
  - Merit list (class or section order, positions, Passed/Failed).
  - Printable tabulation sheet (marks + grade per subject, failures in red, "Abs").
  - Statistics (tiles, grade bars, by section, by subject).
  - Section filter.
- [x] `…/results/report-card`: printable A4 report card:
  - Letterhead and student details.
  - Per-subject CQ / MCQ / practical / obtained / grade / point, with split columns only when used.
  - Total, GPA, grade, result, positions, signature lines.
- [x] `student/results` and `parent/results`: published results with GPA/grade/position and the report card (print)
- [x] Typed `resultService` in `services/`; nav entries (admin, teacher, student, parent); dashboard "My results" / "Results" card

## Acceptance criteria

- [x] Results are computed from marks with correct grade, GPA and class position
- [x] Draft/review/approved results are not publicly visible (nor to students/parents)
- [x] Only `published` results are exposed outside the results screens (`Result::published()`; `/results/me`, widgets)
- [x] The publish action is recorded in `audit_logs` (user, timestamp, exam, IP)
- [x] Position/merit ordering is correct within class-section (ties share a position)
- [x] Tests written and passing:
  - 4 results tests: calculation, grade/GPA/position, ties, absent and fails, the fail band, tabulation, statistics, teacher section and year scope, processing fail-closed, publish gate, draft invisibility, parent only sees own child, grade snapshot, stale scale, out-of-step marks, audit, unpublish/return and the freeze.
  - 11 marks tests. 264 backend tests in total.
- [x] Security review passed (staged workflow enforced, publish scoped, no draft leakage). No critical/high findings; fixed:
  - M1: a reopen racing a workflow step could leave results past draft over editable marks → exam lock first; marks frozen while results are past draft; forward steps re-check all sheets submitted.
  - M2: teachers could read past years' results of their sections → current year only.
  - M3: processing relied on the permission alone → fails closed for teachers.
  - L1: a scale switch or edit left results graded the old way → switch refused past draft (drafts cleared); forward steps refuse results calculated before the scale's last change.
  - L2: section move vs marks save could race → the enrollment row and the roll are locked.
  - L3: a student with no applicable paper counted as passed → fails; marks on another section's paper are refused (422).
  - L4: a mark above pass marks but in the fail band passed → the bottom band is the fail band.
  - L5: rounding could lift a mark over a cut-off → bands compared exactly in integers.
  - L6: the overview showed teachers other sections' figures → own sections only.
  - Not done, for deployment: set trusted proxies so audit IPs aren't the proxy's. Optional: require the approver to be someone other than the processor.

## Notes

**Calculation rules:**
- **Subject grade:** percentage of the paper's full marks, banded on the exam's own grade scale (so historical exams keep their grading).
- **Failing a subject:** a subject below its pass marks, or absent, fails. It gets the scale's bottom band whatever the percentage says.
- **GPA:** the mean of the subject points, rounded half-up to 2 decimals; 0 with any failed subject.
- **Overall letter:** the band with the highest point ≤ GPA, or the bottom band on a fail.
- **The bottom band is the fail band:** a mark landing in it fails even at or above the paper's pass marks. Bands are compared exactly (no rounding before the cut-off).
- **Positions:** only students who passed every subject get a position. They're ordered by GPA, then total marks; equal GPA and total share a position (1, 1, 3). Positions are kept per section and across the class.
- **Who gets a result:** students with submitted marks on the class's papers. A paper they have no mark for counts as absent.

**Not modelled yet:**
- An optional / 4th subject.
- Per-part pass marks (pass CQ and MCQ separately).
- A grace mark policy.

**Not clicked through in the browser by Claude:** the user runs the dev servers. Checked instead:
- Feature tests.
- Larastan (0 errors).
- ESLint and `tsc`.
- Overview, merit and statistics queries against the dev MariaDB.
