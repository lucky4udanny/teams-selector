# Documentation index

- [Local Docker / Sail](LOCAL_DOCKER.md) — Forge-aligned dev stack (PHP 8.4, PostgreSQL, Redis, Mailpit, Node 22); hardened `docker/sail` image for fewer Scout CVEs. `composer run docker:setup` runs `composer install` first so Sail is available on a fresh clone. `.env.example` uses Docker service hostnames (`pgsql`, `redis`, `mailpit`); host-only dev should uncomment SQLite/loopback overrides.
- **PHPUnit DB:** `phpunit.xml` forces `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` so host `composer test` works without Sail; `.env` may still use PostgreSQL for local app dev.
- **Org nav brand:** `OrganizationLayout` shows `logo_url` **or** `organization.name`, never both.
- **Branding form:** `ColorInput.vue` (swatch + picker + hex) on `Organizations/Show.vue`; styling follows [Tailwind Plus](https://tailwindcss.com/plus) Application UI input patterns (ring inset, split control). Live org UI: `.ts-org-branded` maps primary → buttons/inputs/nav, accent → active tab underline.
- **Branding:** TeamForge palette and SVG assets under `public/`; Tailwind tokens in `resources/css/app.css` (`brand-navy`, `brand-blue`, `brand-orange`, …). See [teams-selector](features/teams-selector.md).
- **Home route:** `/` redirects unauthenticated visitors to `/login` (Inertia `Auth/Login`); there is no public Laravel Welcome landing page.
- [Forge deployment](DEPLOY_FORGE.md) — includes deploy script ordering (`storage/framework/views` before `view:clear` / `optimize`), `VIEW_COMPILED_PATH` pitfalls, and **Cloudflare**: use **Full (strict)** SSL (not Flexible) to prevent redirect loops; app trusts proxies for `X-Forwarded-Proto`.
- Domain migrations use **unique sequential timestamps** per file so table order is explicit (see [teams-selector](features/teams-selector.md)); avoid reusing the same `Y_m_d_His` prefix for multiple files.
- Laravel 11+ slim `app/Http/Controllers/Controller.php` may omit **`AuthorizesRequests`**; without it, `$this->authorize()` in controllers throws — add the trait to the base controller.
