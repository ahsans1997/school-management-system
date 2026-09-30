# Module 30 — Navigation, Theme & SEO

- **Status:** ⬜ Not started
- **Phase:** Phase 5 — Website CMS
- **Completed on:** —
- **Owner agent:** `module-builder`

> Configurable public-site chrome: header/footer navigation menus, a swappable theme system that changes presentation only, and global SEO/Open Graph settings with sitemap and robots generation — all cached and invalidated on change.

## Scope

Includes: navigation menus (spec §32) — header/footer/dropdown items with label, url, nesting and ordering; the theme system (spec §33) — a theme registry, active theme selection and per-theme config (colors, typography, layout); global SEO settings (spec §58, §60) — meta/OG defaults, canonical base, robots, social links, plus `sitemap.xml` and `robots.txt` generation. All three are cached (spec §60) and invalidated on change.

Excludes: page content and per-page SEO overrides (owned by `22 — Website Pages`); homepage section layout (Homepage builder). Per spec §33 switching a theme changes look only — the underlying CMS data is untouched (spec §68).

## Dependencies

`04 — School Settings` (site identity, socials), `22 — Website Pages` (published content feeding the sitemap + website.* permissions).

## Database (tables owned / referenced)

- `website_menus` — id, location enum(header, footer), label, url nullable, page_slug nullable, parent_id (self FK nullable, for dropdowns), sort_order int, is_external bool default false, target enum(_self,_blank) default _self, status enum(active,hidden), timestamps. Index (location, parent_id, sort_order).
- `website_themes` — id, key (unique), name, config JSON (colors/typography/layout), is_active bool, timestamps. Exactly one row is_active=true.
- `website_settings` — singleton: seo_title, seo_description, og_title, og_description, og_image_path, canonical_base_url, robots_directives, social_links JSON, sitemap_enabled bool, timestamps.

## Permissions

`website.theme` (spec §6) — list/switch/configure themes. `website.manage` — menus and SEO settings. Public read endpoints require no authentication.

## API endpoints

| Method | Path (`/api/...`) | Permission | Notes |
|---|---|---|---|
| GET/POST/PUT/DELETE | /website/menus | website.manage | manage items; invalidate nav cache |
| GET | /website/themes | website.theme | list registered themes |
| PUT | /website/themes/active | website.theme | switch active theme; invalidate cache |
| PUT | /website/themes/{key} | website.theme | edit theme config |
| GET | /website/settings | website.manage | SEO/OG settings |
| PUT | /website/settings | website.manage | update; invalidate cache |
| GET | /public/navigation | — (public) | cached menu tree |
| GET | /public/settings | — (public) | cached SEO + active-theme presentation config (non-sensitive) |
| GET | /sitemap.xml | — (public) | generated from published pages/notices/events |
| GET | /robots.txt | — (public) | generated from settings |

## Backend tasks

- [ ] Migrations + models for menus, themes, settings (self-FK nesting, unique theme key, single-active guard)
- [ ] Form Requests (validation + `authorize()`) for menus, theme switch/config, settings
- [ ] Actions/Services: menu tree builder, activate-theme, settings update, cache invalidation
- [ ] API Resources (public nav tree, public settings — exclude internal fields)
- [ ] Cache read-through for nav/theme/settings (spec §60); bust on any change
- [ ] Sitemap + robots generators (built from published content; regenerate/bust on publish)
- [ ] Routes: `/website/*` JWT + `permission:` middleware; `/public/*`, sitemap, robots unauthenticated

## Frontend tasks

- [ ] `admin/website/navigation` — menu builder (nest, order, external links)
- [ ] `admin/website/theme` — theme picker + config editor; `admin/website/seo` — SEO/OG form
- [ ] Public layout consumes `/public/navigation` and `/public/settings`; theme drives presentation tokens
- [ ] Typed `navigationService` / `themeService` / `seoSettingsService`

## Acceptance criteria

- [ ] Menus are configurable and served as a nested tree to the public nav
- [ ] Switching theme changes presentation only; CMS data is unchanged
- [ ] SEO/OG fields are output in public page meta (title, description, OG, canonical)
- [ ] `sitemap.xml` is generated from published content; `robots.txt` reflects settings
- [ ] Settings/nav/theme are cached and invalidated on change
- [ ] Tests written and passing (menu tree, theme-switch data-invariance, SEO output, sitemap, cache bust)
- [ ] Security review passed (website.theme/website.manage gated, no secrets in public settings)

## Notes

Theme config is presentation data consumed by Next.js; it holds no content, honouring "enter data once, use everywhere" (spec §68). Enforce a single active theme at the DB/service layer. Sitemap and robots are cache-friendly (spec §60) and should regenerate when publishable content changes rather than on every request.
