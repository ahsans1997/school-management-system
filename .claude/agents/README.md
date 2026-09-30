# Agents roster

Specialized subagents for building the School Management System. Delegate a self-contained slice of work to the matching agent rather than doing everything in the main thread.

| Agent | Use it for |
|---|---|
| `laravel-backend-engineer` | Laravel 13 code: models, controllers, Form Requests, API Resources, Actions/Services, Policies, Jobs, Events, routes. |
| `nextjs-frontend-engineer` | Next.js (App Router, TypeScript): pages, components, data fetching, forms, portal UIs, public website. |
| `database-architect` | Schema design, migrations, foreign keys, indexes, money/decimal columns, soft deletes, transactions. |
| `api-designer` | REST API contract under `/api`: routes, request/response shapes, resource formatting, pagination, error envelope. |
| `module-builder` | Orchestrating a **whole module** end-to-end (DB → API → frontend → acceptance) and updating the progress tracker. |
| `security-reviewer` | Auth/permission/policy review, injection/XSS, file-upload validation, financial-transaction safety, public-endpoint data exposure. |
| `qa-test-engineer` | Writing and running backend (PHPUnit/Pest) and frontend tests; verifying acceptance criteria per module. |

## Conventions every agent follows

- Treat [school_management_system_full_architecture.md](../../school_management_system_full_architecture.md) as the authoritative spec.
- Read the relevant `docs/modules/NN-*.md` before building, and respect the build order in `docs/PROGRESS.md`.
- Enforce the architecture rules in [CLAUDE.md](../../CLAUDE.md): backend layering, two-layer authorization, decimal money + transactions, staged result/CMS workflows.
- Report back concisely: what changed, what's verified, what's left — not a file-by-file dump.
