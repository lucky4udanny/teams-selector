# Changelog

## Unreleased

- **Fix (tests):** Restore `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` in `phpunit.xml` so `composer test` / `php artisan test` do not require Docker PostgreSQL when `.env` defaults to `pgsql`.
- **Local Docker (Forge parity):** Laravel Sail with PHP 8.4, PostgreSQL 18, Redis, Mailpit, Node 22, and a `queue` worker service. Documented in `docs/LOCAL_DOCKER.md`; `composer run docker:setup` for first-time container setup.
- **Docker security:** Hardened `docker/sail/Dockerfile` (no Playwright/FFmpeg, `runuser` instead of `gosu`, slimmer PHP set, `redis:7.4-alpine`). Scout: app image **3C/20H → 0C/1H** vs stock Sail.
- **Branding (TeamForge):** Site-wide design refresh using assets in `public/` (`Logo.svg`, `Wordmark.svg`, `icon-detail.svg`, brand blues/orange). Plus Jakarta Sans typography, design tokens in `resources/css/app.css`, split-panel login with cropped icon-detail hero, updated layouts and components.
- **Auth / home:** `/` redirects guests to the Breeze login page (`/login`) instead of the default Laravel Welcome screen; authenticated users go to the dashboard. Logout returns to login. Guest layout shows `APP_NAME`.
- **Cloudflare / HTTPS:** `trustProxies(at: '*')` in `bootstrap/app.php` so requests behind Cloudflare honor forwarded HTTPS; pair with Cloudflare **SSL/TLS = Full or Full (strict)** (not Flexible) to avoid redirect loops. Documented in `docs/DEPLOY_FORGE.md`.
- **Migrations:** Domain migrations use unique sequential timestamps `2026_04_19_155540`–`155545` (organizations → selection_drafts → approved_selections → members → organization_user → rules) so order does not depend on filename sorting. **If a database already recorded the old migration filenames**, update the `migration` column in `migrations` to match the new names, or rollback domain migrations before redeploying—otherwise Laravel may try to re-run creates.
- **Forge / `view:clear`:** Published `config/view.php` and set `view.compiled` to `env('VIEW_COMPILED_PATH') ?: storage_path('framework/views')` so the path is never empty when `storage/framework/views` is missing on first deploy (default `realpath()` was `false` and triggered `View path not found.`).
- Initial Teams Selector application: Laravel 13, Breeze (Inertia + Vue), multi-tenant organizations, members, weighted rules, draft generation with penalty reporting, approve snapshots with branding, CSV/XLSX export, print view, org users and branding (logo + colors).
- Toolchain: **Vite 8**, **Tailwind CSS v4** (`@tailwindcss/vite`), **Vue 3.5**, **laravel-vite-plugin 3**, current `@inertiajs/vue3` / **axios** / **ziggy-js**. **`package.json` `engines.node`: `>=22.12.0`** (aligns with Vite 8); **`.nvmrc`** set to **22** (use Node 22 LTS or newer, e.g. 23.x, on dev/CI — run `npm ci && npm run build` with that Node).
- Fix: restore **`AuthorizesRequests`** on base `Controller` so `$this->authorize()` works (Laravel 11+ slim skeleton omitted the trait).
