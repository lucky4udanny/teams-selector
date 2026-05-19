# Local development (Docker / Forge parity)

This project uses [Laravel Sail](https://laravel.com/docs/sail) so the local stack matches [Forge deployment](DEPLOY_FORGE.md) as closely as practical.

## Stack comparison

| Component | Forge (production) | Local (Sail) |
|-----------|-------------------|--------------|
| PHP | 8.3+ (8.4 OK) | 8.4 (`compose.yaml`) |
| Database | PostgreSQL (recommended) | PostgreSQL 18 |
| Redis | Optional | Included |
| Node (asset build) | ≥ 22.12 | 22 (pinned in `compose.yaml`) |
| Mail | SMTP / provider | [Mailpit](http://localhost:8025) |
| Web server | Nginx + PHP-FPM | PHP built-in server (dev only) |
| Queue worker | Supervisor daemon | `queue` service in `compose.yaml` |

The web server difference is intentional: Sail’s dev container is lighter than reproducing Nginx + FPM locally. Application code, PHP extensions, PostgreSQL, and Redis behave the same as on Forge.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker Engine + Compose v2)
- Git clone of this repo

## First-time setup

1. Copy environment file and align with Sail (PostgreSQL + Mailpit):

   ```bash
   cp .env.example .env
   ```

   Ensure these values are set (already in `.env.example`):

   - `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, `DB_PORT=5432`
   - `DB_DATABASE=laravel`, `DB_USERNAME=sail`, `DB_PASSWORD=password`
   - `REDIS_HOST=redis`
   - `MAIL_HOST=mailpit`, `MAIL_PORT=1025`, `MAIL_MAILER=smtp`

2. Add Sail user IDs (Linux; on macOS you can often omit or use `id -u` / `id -g`):

   ```bash
   echo "WWWGROUP=$(id -g)" >> .env
   echo "WWWUSER=$(id -u)" >> .env
   ```

3. Install PHP dependencies (on the host, once):

   ```bash
   composer install
   ```

4. Start containers and run app setup **inside** Sail:

   ```bash
   ./vendor/bin/sail up -d
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate
   ./vendor/bin/sail artisan storage:link
   ./vendor/bin/sail npm ci
   ./vendor/bin/sail npm run build
   ```

5. Open **http://localhost** (default `APP_PORT=80`).

   If port **1025** or **8025** is already used (another Mailpit stack), set in `.env`:

   ```env
   FORWARD_MAILPIT_PORT=1125
   FORWARD_MAILPIT_DASHBOARD_PORT=8125
   ```

   Then open Mailpit at http://localhost:8125.

## Day-to-day commands

| Task | Command |
|------|---------|
| Start stack | `./vendor/bin/sail up -d` |
| Stop stack | `./vendor/bin/sail down` |
| Run Artisan | `./vendor/bin/sail artisan …` |
| Run tests | `./vendor/bin/sail artisan test --configuration=phpunit.sail.xml` (PostgreSQL `testing` DB; host `composer test` still uses SQLite via `phpunit.xml`) |
| Vite dev server | `./vendor/bin/sail npm run dev` |
| View mail | http://localhost:8025 (Mailpit; or `:8125` if you changed `FORWARD_MAILPIT_DASHBOARD_PORT`) |
| PostgreSQL CLI | `./vendor/bin/sail psql` |
| Redis CLI | `./vendor/bin/sail redis` |

Optional shell alias:

```bash
alias sail='./vendor/bin/sail'
```

## Queue worker

Forge runs `php artisan queue:work` under Supervisor. Locally, the `queue` service in `compose.yaml` does the same. The app defaults to `QUEUE_CONNECTION=database`; switch to `redis` in `.env` if you want to mirror a Redis-backed queue on Forge.

## Host-only development (no Docker)

You can still use SQLite on the host: uncomment the SQLite block in `.env.example`, set `DB_CONNECTION=sqlite`, and run `composer run dev` (requires PHP 8.3+, Node ≥ 22.12). This is faster for small changes but **differs** from production (PostgreSQL).

## Troubleshooting

- **Port 80 in use:** set `APP_PORT=8080` in `.env` and use http://localhost:8080.
- **Ports 1025, 6379, or 5432 in use** (other Docker projects): set `FORWARD_MAILPIT_PORT`, `FORWARD_REDIS_PORT`, and/or `FORWARD_DB_PORT` in `.env` (e.g. `1125`, `6380`, `5433`). Internal service hostnames (`mailpit`, `redis`, `pgsql`) stay the same.
- **Permission errors on `storage/`:** `./vendor/bin/sail shell` then `chmod -R ug+rwx storage bootstrap/cache`.
- **Fresh database:** `./vendor/bin/sail artisan migrate:fresh` (destroys data).
- **Rebuild after `compose.yaml` changes:** `./vendor/bin/sail build --no-cache` then `sail up -d`.

See also [DEPLOY_FORGE.md](DEPLOY_FORGE.md) for production deploy steps.

## Image security (Docker Scout)

The app image is built from `docker/sail/Dockerfile` (hardened Sail runtime), not the stock vendor image:

- Removed Playwright browser deps, FFmpeg, and unused PHP extensions (smaller attack surface).
- Replaced `gosu` (Go binary with stale CVEs) with `runuser`.
- `apt-get upgrade` on every build; Node **22**; `redis:8-alpine` (do not downgrade Redis while `sail-redis` volume exists — RDB format mismatch).

After changes, Scout on the app image dropped from **3 critical / 20 high** to **0 critical / 1 high** (mostly npm `picomatch` in Node).

**PostgreSQL** (`postgres:18-alpine`) may still report Go stdlib CVEs in Scout — those come from upstream Postgres/Alpine binaries, not Laravel. Re-pull periodically: `docker pull postgres:18-alpine && ./vendor/bin/sail up -d --force-recreate pgsql`.

Rebuild the app image after `docker/sail` changes:

```bash
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d
```
