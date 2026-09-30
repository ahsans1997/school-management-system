# Build Documentation

This folder is the **module-by-module build plan** for the School Management System. It turns the architecture spec ([../school_management_system_full_architecture.md](../school_management_system_full_architecture.md)) into an ordered, trackable sequence of buildable modules.

## How this is organized

| File / folder | Purpose |
|---|---|
| [PROGRESS.md](PROGRESS.md) | **The live completion tracker.** One checkbox per module, grouped by phase. This is the single source of truth for what's done. |
| [MODULE_TEMPLATE.md](MODULE_TEMPLATE.md) | The template every module doc follows. Copy it when adding a new module. |
| [modules/](modules/) | One doc per module (`NN-<module>.md`), numbered in build order, each with scope, dependencies, DB tables, API endpoints, backend/frontend tasks, and acceptance criteria. |

## The workflow

1. **Pick the next unchecked module** in [PROGRESS.md](PROGRESS.md). Build in order — modules depend on earlier ones.
2. **Open its doc** in `modules/` and read the whole thing (scope, dependencies, tasks, acceptance criteria).
3. **Build the vertical slice**: database → API → backend logic → frontend → tests. The `module-builder` agent can drive this; specialized agents (`database-architect`, `api-designer`, `laravel-backend-engineer`, `nextjs-frontend-engineer`, `security-reviewer`, `qa-test-engineer`) handle the pieces.
4. **Mark it complete** — only when backend + frontend + every acceptance criterion pass:
   - In the module doc, set `Status:` to `✅ Complete`, fill in the completion date, and check every acceptance box.
   - In [PROGRESS.md](PROGRESS.md), change the module's `- [ ]` to `- [x]` and update its status column.

   A module is **not** complete until it is checked in **both** places. If part of it is deferred, leave it unchecked and note what's outstanding in the module doc.

## Status legend

| Symbol | Meaning |
|---|---|
| ⬜ Not started | No work begun |
| 🟡 In progress | Actively being built |
| 🔵 In review | Built; under QA / security review |
| ✅ Complete | Backend + frontend + acceptance criteria all pass, checked in PROGRESS.md |
| ⏸️ Blocked | Waiting on a dependency or decision (note the blocker) |

## Phases (build order)

The modules follow the architecture's recommended phases (§64, §67):

- **Phase 1 — Foundation:** setup, auth, roles/permissions, users, school settings, dashboard.
- **Phase 2 — Academic Core:** academic years, classes, sections, subjects, guardians, students, teachers, enrollments.
- **Phase 3 — Academic Operations:** attendance, routine, exams, marks, results.
- **Phase 4 — Finance:** fees, payments, expenses & basic accounts.
- **Phase 5 — Website CMS:** pages, homepage, sliders, notices, events, gallery, teacher profiles, downloads, navigation/theme/SEO, admission, public result.
- **Phase 6 — Portals:** parent, teacher, student.
- **Phase 7 — Cross-cutting:** reports, notifications, audit logs.

Optional V2 modules (library, transport, hostel, inventory, payroll, advanced accounting) are out of scope for V1 and not tracked here yet.
