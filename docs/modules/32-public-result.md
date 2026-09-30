# Module 32 — Public Result

- **Status:** ⬜ Not started
- **Phase:** Phase 5 — Website CMS
- **Completed on:** —
- **Owner agent:** `module-builder`

> Public result lookup at schooldomain.com/result: a visitor submits student id/roll + exam + year and receives only published results with a minimal field set — hardened against enumeration and scraping.

## Scope

Includes (spec §35, §56): a single public lookup endpoint that accepts student_id or roll + exam + academic year and returns a minimal result view. Only **published** results are ever returned (module 18 staging; spec §56 forbids exposing draft/teacher-entered marks). Response is limited to name, roll, subject marks, grade and GPA. The endpoint is rate-limited against scraping and returns a generic not-found for any invalid or unpublished lookup.

Excludes: draft/unpublished marks (modules 16/18), report-card/tabulation PDF generation (Results module), and authenticated student/parent portal result views (Portal modules) which may show richer data behind login.

## Dependencies

`18 — Results` (published-result staging + publish flag), `06 — Academic Years` (year selector for the lookup).

## Database (tables owned / referenced)

- Owns no primary table — it is a read-only, minimal projection over published results.
- References: `results` / `marks` (filtered `where published = true`), `exams`, `students` (name/roll only), `academic_years`.
- Optional `public_result_lookups` — id, ip, lookup_keys (hashed), created_at — a lightweight audit/throttle log for scraping detection (no student PII).

## Permissions

None — fully public (no auth, spec §47). There is no permission that widens the response; the invariant is enforced in code: published-only + minimal projection.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| POST | /public/results | — (public, throttled) | body: { student_id \| roll, exam_id, academic_year_id }; returns minimal published result or generic not-found |

## Backend tasks

- [ ] Form Request validating the lookup keys (student_id or roll, exam, academic year)
- [ ] Use POST (not GET) so identifiers never appear in the URL/query string (spec §51)
- [ ] Rate limiting (`throttle`) per IP; optional CAPTCHA challenge after N failed attempts (anti-scraping)
- [ ] Query scoped to `published = true`; unpublished/draft/missing all resolve to the **same** generic not-found (no oracle for enumeration)
- [ ] `PublicResultResource` — projects name, roll, class/section label, per-subject marks, grade, GPA ONLY; no phone/address/id numbers/guardian
- [ ] Optional lookup audit write for abuse monitoring (spec §63)
- [ ] Route under `/public/results`, unauthenticated + throttled

## Frontend tasks

- [ ] `(public)/result` — lookup form (student id/roll, exam, academic year) + result card
- [ ] Generic, non-revealing error message for not-found
- [ ] Typed `publicResultService` (single lookup method)
- [ ] Client-side throttling/disable on rapid repeat submits

## Acceptance criteria

- [ ] Only published results are returned
- [ ] Draft/unpublished results are never exposed
- [ ] Response is minimal (name, roll, subject marks, grade, GPA) — no phone/address/PII
- [ ] Endpoint is rate-limited against scraping
- [ ] Invalid, unpublished or unknown lookups return an identical generic not-found (no enumeration signal)
- [ ] Tests written and passing (published-only filter, minimal projection, throttle, generic not-found)
- [ ] Security review passed (public exposure minimal, enumeration/scraping resistance, no PII in query)

## Notes

Spec §35 stresses avoiding exposure of unnecessary student data; §56 forbids showing draft marks. The generic not-found must be indistinguishable whether the student does not exist, the exam does not match, or the result is unpublished — otherwise the endpoint becomes an enumeration oracle. POST keeps identifiers out of logs/URLs. This module reuses published results (spec §68) and adds no marks of its own.
