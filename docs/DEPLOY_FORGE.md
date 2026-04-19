# Deploying on Laravel Forge

## Server

- PHP 8.3+ (8.4 supported), Composer 2.
- **Node.js ≥ 22.12** (recommended: **22 LTS** or current stable such as **23.x**) for `npm ci` / `npm run build`. The repo pins this via `package.json` `engines` and `.nvmrc` (22). Forge “Application” → Node version should match.
- Database: PostgreSQL recommended; SQLite works for small installs.
- Redis optional (queues if you offload heavy generation later).

## Environment

- Copy `.env.example` to `.env` on the server and set `APP_KEY`, `APP_URL`, database credentials, and mail settings.
- `php artisan migrate --force` on deploy.
- `php artisan storage:link` once per server so organization logos in `storage/app/public` are web-accessible.

## Deploy script order

- Ensure Laravel’s writable storage dirs exist **before** `php artisan optimize`, `view:clear`, or `config:cache` (Forge’s default deploy often runs these). Example: `mkdir -p storage/framework/{views,sessions,cache}` and `chmod -R ug+rwx storage bootstrap/cache` if needed.
- If you ever set `VIEW_COMPILED_PATH` in `.env`, it must be a non-empty absolute path; an empty value breaks `php artisan view:clear` with `View path not found.` The app ships `config/view.php` so the compiled path falls back to `storage/framework/views` when the env var is unset.

## Build

- Install Composer dependencies: `composer install --no-dev --optimize-autoloader`.
- Install Node dependencies and build frontend: `npm ci && npm run build` (or build assets in CI and deploy `public/build`).

## Queues and scheduler (optional)

- If you add queued jobs, configure a Forge daemon or `queue:work` with Supervisor.
- For scheduled tasks, add the Laravel scheduler to Forge’s cron: `* * * * * cd /home/forge/site && php artisan schedule:run`.

## Backups

- Enable Forge automated database backups for PostgreSQL.
- Include `storage/app` in backups if you store logos only on local disk (or use S3 and document `FILESYSTEM_DISK`).

## Security

- Serve over HTTPS only; set `SESSION_SECURE_COOKIE=true` in production.
- Restrict `.env` and storage permissions per Forge defaults.
