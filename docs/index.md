# Documentation index

- [Local Docker / Sail](LOCAL_DOCKER.md) — Forge-aligned dev stack (PHP 8.4, PostgreSQL, Redis, Mailpit, Node 22); hardened `docker/sail` image for fewer Scout CVEs. `composer run docker:setup` runs `composer install` first so Sail is available on a fresh clone. `.env.example` uses Docker service hostnames (`pgsql`, `redis`, `mailpit`); host-only dev should uncomment SQLite/loopback overrides.
- **PHPUnit DB:** `phpunit.xml` forces `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` so host `composer test` works without Sail; `.env` may still use PostgreSQL for local app dev.
- **Org nav brand:** `OrganizationLayout` shows `logo_url` **or** `organization.name`, never both.
- **Branding form:** `ColorInput.vue` (swatch + picker + hex) on `Organizations/Show.vue`; styling follows [Tailwind Plus](https://tailwindcss.com/plus) Application UI input patterns (ring inset, split control). Live org UI: `.ts-org-branded` maps primary → buttons/inputs/nav, accent → active tab underline.
- **Branding:** TeamForge palette and SVG assets under `public/`; Tailwind tokens in `resources/css/app.css` (`brand-navy`, `brand-blue`, `brand-orange`, …). See [teams-selector](features/teams-selector.md).
- **Home route:** `/` redirects unauthenticated visitors to `/login` (Inertia `Auth/Login`); there is no public Laravel Welcome landing page.
- [Forge deployment](DEPLOY_FORGE.md) — includes deploy script ordering (`storage/framework/views` before `view:clear` / `optimize`), `VIEW_COMPILED_PATH` pitfalls, and **Cloudflare**: use **Full (strict)** SSL (not Flexible) to prevent redirect loops; app trusts proxies for `X-Forwarded-Proto`.
- Domain migrations use **unique sequential timestamps** per file so table order is explicit (see [teams-selector](features/teams-selector.md)); avoid reusing the same `Y_m_d_His` prefix for multiple files.
- **Members import:** `Members/Index` accepts `.csv` or `.xlsx`; `MemberCsvImportParser` auto-detects encoding and delimiter. Route `organizations.members.import`, `forceFormData`, `PrimaryButton type="submit"`.
- **Inertia event UI:** Event **Show** tabs are driven by `?tab=` (`details`, `roster`, `rules`, `drafts`, `final`); roster/members and rules mutations use the scoped `organizations.events.*` web routes (patch/bulk/post), and the final tab builds export URLs with a `columns=` query matching `EventExportController`’s allowed column keys.
- **Violation messages:** `ViolationFormatter` enriches `state.violations` with a `formatted` field for screen, print, and CSV/XLSX (`violations=1|0` query param). See [teams-selector](features/teams-selector.md).
- **Pair history:** `PairHistoryService::pairsForPriorEvent` tolerates corrupt `team_indices` in stored draft JSON when building group-scope repeat pairs.
- **Rule ordering:** `Event::recalculateRuleSortOrders()` runs after duplicate and rule mutations so UI/solver order matches weight.
- **DateInput:** With `model-type="yyyy-MM-dd"`, bind string dates only—see [teams-selector](features/teams-selector.md).
- **PrimaryButton:** defaults to `type="submit"` for Inertia/Laravel form posts; use `type="button"` when the control is outside a submit flow (modals, `@click` handlers).
- **Form feedback:** Catalog and event flows flash success/errors; shared client validation in `resources/js/utils/formValidation.js` (details, rules, drafts, roster empty-selection alerts). Roster bulk add: `RosterMemberPicker.vue`; sort via `rosterSort.js` + localStorage per org.
