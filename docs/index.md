# Documentation index

- [Local Docker / Sail](LOCAL_DOCKER.md) — Forge-aligned dev stack (PHP 8.4, PostgreSQL, Redis, Mailpit, Node 22); hardened `docker/sail` image for fewer Scout CVEs.
- **PHPUnit DB:** `phpunit.xml` forces `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` so host `composer test` works without Sail; `.env` may still use PostgreSQL for local app dev.
- **Branding:** TeamForge palette and SVG assets under `public/`; Tailwind tokens in `resources/css/app.css` (`brand-navy`, `brand-blue`, `brand-orange`, …). See [teams-selector](features/teams-selector.md).
- **Home route:** `/` redirects unauthenticated visitors to `/login` (Inertia `Auth/Login`); there is no public Laravel Welcome landing page.
- [Forge deployment](DEPLOY_FORGE.md) — includes deploy script ordering (`storage/framework/views` before `view:clear` / `optimize`), `VIEW_COMPILED_PATH` pitfalls, and **Cloudflare**: use **Full (strict)** SSL (not Flexible) to prevent redirect loops; app trusts proxies for `X-Forwarded-Proto`.
- Domain migrations use **unique sequential timestamps** per file so table order is explicit (see [teams-selector](features/teams-selector.md)); avoid reusing the same `Y_m_d_His` prefix for multiple files.
- Laravel 11+ slim `app/Http/Controllers/Controller.php` may omit **`AuthorizesRequests`**; without it, `$this->authorize()` in controllers throws — add the trait to the base controller.
